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

/**
 * KingbaseES MySQL 兼容模式数据库连接器。
 */
class KingbaseMysql extends Mysql
{
    use KingbaseConnection;

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
     * 读取 MySQL 兼容模式下的数据表字段元数据。
     *
     * 从 information_schema 及系统目录中组合字段类型、可空性、默认值、主键、
     * 自增序列和注释，并转换成 ThinkORM 统一字段结构。
     *
     * @param string $tableName 表名，可包含 Schema 和别名
     *
     * @return array 以字段名为键的 ThinkORM 字段元数据
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);
        $tableName = trim($tableName, '`"');

        if (str_contains($tableName, '.')) {
            [$schema, $tableName] = explode('.', $tableName, 2);
            $schema = trim($schema, '`"');
            $tableName = trim($tableName, '`"');
        } else {
            $schema = $this->getSchemaName();
        }

        $schemaValue = $this->quoteString($schema);
        $tableValue = $this->quoteString($tableName);

        $sql = <<<SQL
SELECT
    c.column_name AS "field",
    c.data_type AS "type",
    format_type(a.atttypid, a.atttypmod) AS "full_type",
    c.is_nullable AS "is_nullable",
    c.column_default AS "default",
    CASE WHEN pk.column_name IS NOT NULL THEN 1 ELSE 0 END AS "primary",
    a.attidentity AS "attidentity",
    pg_get_serial_sequence(format('%I.%I', n.nspname, cls.relname), a.attname) AS "serial_sequence",
    d.description AS "comment"
FROM
    information_schema.columns c
    LEFT JOIN (
        SELECT
            kcu.table_schema,
            kcu.table_name,
            kcu.column_name
        FROM
            information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON tc.constraint_name = kcu.constraint_name
             AND tc.table_schema = kcu.table_schema
             AND tc.table_name = kcu.table_name
        WHERE
            tc.constraint_type = 'PRIMARY KEY'
    ) pk
      ON c.table_schema = pk.table_schema
     AND c.table_name = pk.table_name
     AND c.column_name = pk.column_name
    LEFT JOIN pg_namespace n
      ON n.nspname = c.table_schema
    LEFT JOIN pg_class cls
      ON cls.relnamespace = n.oid
     AND cls.relname = c.table_name
    LEFT JOIN pg_attribute a
      ON a.attrelid = cls.oid
     AND a.attname = c.column_name
    LEFT JOIN pg_description d
      ON d.objoid = cls.oid
     AND d.objsubid = a.attnum
WHERE
    c.table_schema = {$schemaValue}
    AND c.table_name = {$tableValue}
ORDER BY
    c.ordinal_position
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info = [];

        foreach ($result as $val) {
            $val = array_change_key_case($val);
            $field = $val['field'];

            $info[$field] = [
                'name' => $field,
                'type' => $val['full_type'] ?: $val['type'],
                'notnull' => 'NO' === strtoupper((string) $val['is_nullable']),
                'default' => $val['default'],
                'primary' => (bool) $val['primary'],
                'autoinc' => '' !== (string) $val['attidentity'] || !empty($val['serial_sequence']),
                'sequence' => $val['serial_sequence'] ?: null,
                'comment' => $val['comment'],
            ];
        }

        return $this->fieldCase($info);
    }

    /**
     * 获取指定 Schema 下的基础表列表。
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
ORDER BY table_name
SQL;

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return array_column($result, 'table_name');
    }

    /**
     * 获取最近一次插入产生的自增主键。
     *
     * PDO_KDB 当前未实现可靠的 lastInsertId()，因此在同一物理连接上直接
     * 调用 LAST_INSERT_ID() 取得 MySQL 兼容模式生成值。
     *
     * @param string|null $sequence 可选序列名，保留父连接器接口兼容性
     *
     * @return mixed 自增主键值，无法取得时返回空字符串
     */
    public function getAutoID(?string $sequence = null)
    {
        return $this->resolveKingbaseGeneratedKey(function () {
            $pdo = $this->linkID->query('SELECT LAST_INSERT_ID() AS id');
            $result = $pdo->fetch(PDO::FETCH_ASSOC);

            return isset($result['id']) ? (string) $result['id'] : '';
        });
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
     * @return string 固定返回 mysql
     */
    protected function getExpectedKingbaseMode(): string
    {
        return 'mysql';
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
}
