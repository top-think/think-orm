<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\db\connector;

use PDO;
use think\db\concern\KingbaseConnection;
use think\model\type\KingbaseDateTime;

/**
 * KingbaseES SQL Server 兼容模式数据库连接器。
 */
class KingbaseSqlsrv extends Sqlsrv
{
    use KingbaseConnection {
        getFieldType as private getKingbaseFieldType;
    }

    /**
     * 将SQL Server兼容模式的DATETIME系列映射到KingbaseES日期时间类型。
     *
     * PDO_KDB可能返回7位小数秒，ThinkORM原生DateTime只接受最多6位微秒；
     * 其他字段类型继续沿用KingbaseES公共绑定和类型映射规则。
     */
    protected function getFieldType(string $type): string
    {
        if (str_starts_with(strtolower(trim($type)), 'datetime')) {
            return KingbaseDateTime::class;
        }

        return $this->getKingbaseFieldType($type);
    }

    /**
     * 根据 ThinkORM 连接配置生成 PDO_KDB DSN。
     *
     * @param array $config 数据库连接配置
     *
     * @return string kdb: 协议 DSN
     */
    protected function parseDsn(array $config): string
    {
        $dsn = 'kdb:host=' . $config['hostname'] . ';dbname=' . $config['database'];

        if (!empty($config['hostport'])) {
            $dsn .= ';port=' . $config['hostport'];
        }

        return $dsn;
    }

    /**
     * 读取 SQL Server 兼容模式下的数据表字段元数据。
     *
     * 分别读取基础列、主键列和 IDENTITY 列后合并为 ThinkORM 统一字段结构，
     * 避免不同系统视图之间的兼容差异影响字段识别。
     *
     * @param string $tableName 表名，可包含 Schema 和别名
     *
     * @return array 以小写字段名为键的 ThinkORM 字段元数据
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);
        [$schema, $tableName] = $this->parseSqlsrvTable($tableName);

        $result = $this->getColumnRows($schema, $tableName);
        $comments = $this->getColumnComments($schema, $tableName);
        $info = [];

        foreach ($result as $val) {
            $val = array_change_key_case($val);
            $field = strtolower($val['field']);

            $info[$field] = [
                'name' => $field,
                'type' => $val['type'],
                'notnull' => 'NO' === strtoupper((string) $val['is_nullable']),
                'default' => $val['default'],
                'primary' => false,
                'autoinc' => false,
                'sequence' => null,
                'comment' => $comments[$field] ?? null,
            ];
        }

        foreach ($this->getPrimaryFields($schema, $tableName) as $field) {
            $field = strtolower($field);
            if (isset($info[$field])) {
                $info[$field]['primary'] = true;
            }
        }

        foreach ($this->getIdentityFields($schema, $tableName) as $field) {
            $field = strtolower($field);
            if (isset($info[$field])) {
                $info[$field]['autoinc'] = true;
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 获取指定 Schema 下的业务基础表列表。
     *
     * 查询时过滤 KingbaseES SQL Server 兼容模式中的内部辅助表。
     *
     * @param string $dbName Schema 名，为空时使用当前连接的 Schema
     *
     * @return array 表名列表
     */
    public function getTables(string $dbName = ''): array
    {
        $schema = $dbName ?: $this->getSchemaName();
        $schemaValue = $this->quoteString($schema);
        $sql = <<<SQL
SELECT table_name
FROM information_schema.tables
WHERE table_schema = {$schemaValue}
  AND table_type = 'BASE TABLE'
  AND table_name NOT LIKE '\_%' ESCAPE '\'
  AND table_name NOT LIKE 'sys\_%' ESCAPE '\'
  AND table_name NOT LIKE 'sysmac\_%' ESCAPE '\'
  AND table_name NOT IN (
      'check_param',
      'check_type',
      'hm_run_t',
      'pg_type_nmap',
      'sys_mview'
  )
ORDER BY table_name
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return array_column($result, 'table_name');
    }

    /**
     * 获取最近一次插入产生的 IDENTITY 主键。
     *
     * PDO_KDB 的 lastInsertId() 不能作为 SQL Server 兼容模式的可靠结果，因此只在
     * 当前 INSERT 已确认使用 Identity 列时，于同一物理连接上依次查询
     * SCOPE_IDENTITY() 和 @@IDENTITY，防止返回先前 INSERT 遗留的会话值。
     *
     * @param string|null $sequence 可选序列名，保留父连接器接口兼容性
     *
     * @return mixed IDENTITY 值，无法取得时返回空字符串
     */
    public function getAutoID(?string $sequence = null)
    {
        return $this->resolveKingbaseGeneratedKey(function () {
            foreach (['SELECT SCOPE_IDENTITY() AS id', 'SELECT @@IDENTITY AS id'] as $sql) {
                try {
                    $pdo = $this->linkID->query($sql);
                    $result = $pdo->fetch(PDO::FETCH_ASSOC);
                    $id = is_array($result) ? ($result['id'] ?? current($result)) : '';

                    if ('' !== $id && false !== $id && null !== $id) {
                        return (string) $id;
                    }
                } catch (\Throwable $e) {
                    // Try the documented fallback on the same physical connection.
                }
            }

            return '';
        });
    }

    /**
     * 使用 SQL Server 兼容语法开启事务。
     *
     * 最外层执行 BEGIN TRANSACTION；内层仅增加事务计数，保持原 Sqlsrv
     * Connector 不使用保存点的逻辑嵌套语义。
     *
     * @return void
     */
    public function startTrans(): void
    {
        $this->initConnect(true);

        if (0 == $this->transTimes) {
            $this->linkID->exec('BEGIN TRANSACTION');
        }

        $this->transTimes++;
        $this->reConnectTimes = 0;
    }

    /**
     * 使用 SQL Server 兼容语法提交事务。
     *
     * 事务计数回到零时才执行物理 COMMIT，以保持 ThinkORM 嵌套事务行为。
     *
     * @return void
     */
    public function commit(): void
    {
        if (0 == $this->transTimes) {
            return;
        }
        $this->initConnect(true);
        if (1 == $this->transTimes) {
            $this->linkID->exec('COMMIT');
        }
        $this->transTimes--;
    }

    /**
     * 使用 SQL Server 兼容语法回滚事务。
     *
     * 最外层执行物理 ROLLBACK；内层仅减少事务计数，不执行局部回滚，
     * 保持原 Sqlsrv Connector 的事务行为。
     *
     * @return void
     */
    public function rollback(): void
    {
        if (0 == $this->transTimes) {
            return;
        }
        $this->initConnect(true);
        if (1 == $this->transTimes) {
            $this->linkID->exec('ROLLBACK');
        }
        $this->transTimes--;
    }

    /**
     * 拆分 SQL Server 风格的 Schema 限定表名。
     *
     * @param string $tableName 表名，可使用 schema.table 及方括号/引号形式
     *
     * @return array{0:string,1:string} Schema 与表名
     */
    protected function parseSqlsrvTable(string $tableName): array
    {
        $tableName = trim($tableName, '`[]"');

        if (str_contains($tableName, '.')) {
            [$schema, $tableName] = explode('.', $tableName, 2);

            return [
                trim($schema, '`[]"'),
                trim($tableName, '`[]"'),
            ];
        }

        return [$this->getSchemaName(), $tableName];
    }

    /**
     * 获取已配置并经服务器确认的 Schema 名称。
     *
     * @return string 当前 Schema 名称
     */
    protected function getSchemaName(): string
    {
        return $this->getKingbaseSchema();
    }

    /**
     * 返回本连接器要求的 KingbaseES 兼容模式。
     *
     * @return string 固定返回 sqlserver
     */
    protected function getExpectedKingbaseMode(): string
    {
        return 'sqlserver';
    }

    /**
     * 从 information_schema 获取指定表的基础列信息。
     *
     * @param string $schema    Schema 名称
     * @param string $tableName 表名
     *
     * @return array 数据库返回的字段记录
     */
    protected function getColumnRows(string $schema, string $tableName): array
    {
        $schemaValue = $this->quoteString($schema);
        $tableValue = $this->quoteString($tableName);

        $sql = <<<SQL
SELECT
    c.column_name AS "field",
    c.data_type AS "type",
    c.is_nullable AS "is_nullable",
    c.column_default AS "default"
FROM
    information_schema.columns c
WHERE
    c.table_schema = {$schemaValue}
    AND c.table_name = {$tableValue}
ORDER BY
    c.ordinal_position
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * 从 KingbaseES 系统目录读取 SQL Server 兼容模式的字段注释。
     *
     * 某些服务端版本未暴露该系统目录时返回空集合，基础字段元数据仍可使用；支持
     * 目录的版本则返回 COMMENT ON COLUMN 写入的真实非空注释。
     *
     * @return array<string, string|null> 以小写字段名为键的注释集合
     */
    protected function getColumnComments(string $schema, string $tableName): array
    {
        $schemaValue = $this->quoteString($schema);
        $tableValue = $this->quoteString($tableName);
        $sql = <<<SQL
SELECT a.attname AS "field", d.description AS "comment"
FROM pg_namespace n
JOIN pg_class cls
  ON cls.relnamespace = n.oid
JOIN pg_attribute a
  ON a.attrelid = cls.oid
LEFT JOIN pg_description d
  ON d.objoid = cls.oid
 AND d.objsubid = a.attnum
WHERE n.nspname = {$schemaValue}
  AND cls.relname = {$tableValue}
  AND a.attnum > 0
  AND NOT a.attisdropped
SQL;

        try {
            $rows = $this->getPDOStatement($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $exception) {
            return [];
        }

        $comments = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row);
            $comments[strtolower((string) $row['field'])] = $row['comment'] ?? null;
        }

        return $comments;
    }

    /**
     * 获取指定表按约束顺序排列的主键字段名。
     *
     * @param string $schema    Schema 名称
     * @param string $tableName 表名
     *
     * @return array 主键字段名列表
     */
    protected function getPrimaryFields(string $schema, string $tableName): array
    {
        $schemaValue = $this->quoteString($schema);
        $tableValue = $this->quoteString($tableName);
        $sql = <<<SQL
SELECT kcu.column_name
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON tc.constraint_catalog = kcu.constraint_catalog
 AND tc.constraint_schema = kcu.constraint_schema
 AND tc.constraint_name = kcu.constraint_name
 AND tc.table_schema = kcu.table_schema
 AND tc.table_name = kcu.table_name
WHERE tc.constraint_type = 'PRIMARY KEY'
  AND tc.table_schema = {$schemaValue}
  AND tc.table_name = {$tableValue}
ORDER BY kcu.ordinal_position
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return array_map('current', $result);
    }

    /**
     * 获取指定表的 IDENTITY 自增字段名。
     *
     * @param string $schema    Schema 名称
     * @param string $tableName 表名
     *
     * @return array IDENTITY 字段名列表
     */
    protected function getIdentityFields(string $schema, string $tableName): array
    {
        $objectName = $schema . '.' . $tableName;
        $objectValue = $this->quoteString($objectName);
        $sql = <<<SQL
SELECT c.name
FROM sys.columns c
WHERE c.object_id = OBJECT_ID({$objectValue})
  AND c.is_identity = 1
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return array_map('current', $result);
    }

    /**
     * 使用当前 PDO_KDB 连接转义元数据查询中的字符串字面量。
     *
     * @param string $value 待转义字符串
     *
     * @return string 包含引号的安全 SQL 字面量
     */
    protected function quoteString(string $value): string
    {
        $this->initConnect(true);

        return $this->linkID->quote($value);
    }

    /**
     * 禁用父 SQL Server 连接器的会话时区设置 SQL。
     *
     * KingbaseES SQL Server 兼容模式未提供父实现依赖的完整时区设置语义，因此返回
     * 空 SQL，日期时间表达式由数据库当前会话处理。
     *
     * @param string $timezone ThinkORM 配置的时区
     *
     * @return string 固定返回空字符串
     */
    protected function getSetTimezoneSql(string $timezone): string
    {
        return '';
    }
}
