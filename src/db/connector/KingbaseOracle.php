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
use think\db\BaseQuery;
use think\db\concern\KingbaseConnection;

/**
 * KingbaseES Oracle 兼容模式数据库连接器。
 */
class KingbaseOracle extends Oracle
{
    use KingbaseConnection;

    /**
     * Oracle 兼容模式默认 PDO 连接参数。
     *
     * KingbaseES 可通过 enable_upper_colname 将结果集列名转换为大写。ThinkORM
     * 应用通常以小写字段名访问数组和模型属性，因此在 PDO 边界统一为小写，避免
     * 数据库参数开关改变 PHP 层字段键。应用显式传入的 params 仍具有更高优先级。
     *
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

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
     * 读取 Oracle 兼容模式下的数据表字段元数据。
     *
     * 未指定 Owner 时查询 USER_* 视图，指定 Owner 时查询 ALL_* 视图，并将字段、
     * 主键、可空性、默认值和注释转换成 ThinkORM 统一字段结构。自动生成标记单独
     * 从 Identity 和默认序列读取，不再将普通主键视为自增。
     *
     * @param string $tableName 表名，可包含 Owner 和别名
     *
     * @return array 以小写字段名为键的 ThinkORM 字段元数据
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);
        [$owner, $tableName] = $this->parseOracleTable($tableName);

        if (null === $owner) {
            $tableValue = $this->quoteString($tableName);
            $sql = <<<SQL
SELECT
    c.column_name AS "field",
    c.data_type AS "type",
    c.nullable AS "nullable",
    c.data_default AS "default",
    CASE WHEN pk.column_name IS NOT NULL THEN 1 ELSE 0 END AS "primary",
    cc.comments AS "comment"
FROM
    user_tab_columns c
    LEFT JOIN (
        SELECT
            cols.table_name,
            cols.column_name
        FROM
            user_constraints cons
            JOIN user_cons_columns cols
              ON UPPER(cons.constraint_name) = UPPER(cols.constraint_name)
             AND UPPER(cons.table_name) = UPPER(cols.table_name)
        WHERE
            UPPER(cons.constraint_type) IN ('P', 'PRIMARY KEY')
    ) pk
      ON c.table_name = pk.table_name
     AND c.column_name = pk.column_name
    LEFT JOIN user_col_comments cc
      ON c.table_name = cc.table_name
     AND c.column_name = cc.column_name
WHERE
    c.table_name = {$tableValue}
ORDER BY
    c.column_id
SQL;
        } else {
            $ownerValue = $this->quoteString($owner);
            $tableValue = $this->quoteString($tableName);
            $sql = <<<SQL
SELECT
    c.column_name AS "field",
    c.data_type AS "type",
    c.nullable AS "nullable",
    c.data_default AS "default",
    CASE WHEN pk.column_name IS NOT NULL THEN 1 ELSE 0 END AS "primary",
    cc.comments AS "comment"
FROM
    all_tab_columns c
    LEFT JOIN (
        SELECT
            cols.owner,
            cols.table_name,
            cols.column_name
        FROM
            all_constraints cons
            JOIN all_cons_columns cols
              ON UPPER(cons.owner) = UPPER(cols.owner)
             AND UPPER(cons.constraint_name) = UPPER(cols.constraint_name)
             AND UPPER(cons.table_name) = UPPER(cols.table_name)
        WHERE
            UPPER(cons.constraint_type) IN ('P', 'PRIMARY KEY')
    ) pk
      ON c.owner = pk.owner
     AND c.table_name = pk.table_name
     AND c.column_name = pk.column_name
    LEFT JOIN all_col_comments cc
      ON c.owner = cc.owner
     AND c.table_name = cc.table_name
     AND c.column_name = cc.column_name
WHERE
    c.owner = {$ownerValue}
    AND c.table_name = {$tableValue}
ORDER BY
    c.column_id
SQL;
        }

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info = [];
        $generatedFields = $this->getGeneratedFields($owner ?? $this->getKingbaseSchema(), $tableName);

        foreach ($result as $val) {
            $val = array_change_key_case($val);
            $field = strtolower($val['field']);

            $info[$field] = [
                'name' => $field,
                'type' => $val['type'],
                'notnull' => 'N' === strtoupper((string) $val['nullable']),
                'default' => $val['default'],
                'primary' => (bool) $val['primary'],
                'autoinc' => array_key_exists($field, $generatedFields),
                'sequence' => $generatedFields[$field] ?? null,
                'comment' => $val['comment'],
            ];
        }

        return $this->fieldCase($info);
    }

    /**
     * 读取实际由数据库自动生成值的列，区分 Identity、默认序列和普通主键。
     *
     * 业务显式写入 seq.NEXTVAL 或设置 sequence() 不改变表定义，因此不在此标记
     * autoinc；显式序列的 CURRVAL 回读仍由 getLastInsID() 负责。
     *
     * @param string $owner Schema 名称
     * @param string $tableName Oracle 表名
     * @return array<string, string|null> 以小写字段名为键、序列名为值的自动生成列集合
     */
    protected function getGeneratedFields(string $owner, string $tableName): array
    {
        $ownerValue = $this->quoteString($owner);
        $tableValue = $this->quoteString($tableName);
        $sql = 'SELECT column_name, is_identity, column_default FROM information_schema.columns'
            . ' WHERE lower(table_schema) = lower(' . $ownerValue . ')'
            . ' AND lower(table_name) = lower(' . $tableValue . ')';
        $rows = $this->getPDOStatement($sql)->fetchAll(PDO::FETCH_ASSOC);
        $fields = [];
        foreach ($rows as $row) {
            $row = array_change_key_case($row);
            $default = (string) ($row['column_default'] ?? '');
            $sequence = $this->extractSequenceFromDefault($default);
            if ('YES' === strtoupper((string) $row['is_identity']) || null !== $sequence) {
                $fields[strtolower($row['column_name'])] = $sequence;
            }
        }
        return $fields;
    }

    /**
     * 获取当前用户或指定 Owner 下的业务表列表。
     *
     * 查询时过滤 KingbaseES Oracle 兼容模式中的内部辅助表，避免其被 ORM 当作
     * 应用数据表使用。
     *
     * @param string $dbName Owner 名，为空时查询当前用户表
     *
     * @return array 表名列表
     */
    public function getTables(string $dbName = ''): array
    {
        if ('' === $dbName) {
            $sql = <<<SQL
SELECT table_name
FROM user_tables
WHERE table_name NOT LIKE '\_%' ESCAPE '\'
  AND table_name NOT LIKE 'SQL\_%' ESCAPE '\'
  AND table_name NOT IN (
      'CHECK_PARAM',
      'CHECK_TYPE',
      'DUAL',
      'HM_RUN_T',
      'PG_TYPE_NMAP',
      'SYS_MVIEW'
  )
ORDER BY table_name
SQL;
        } else {
            $ownerValue = $this->quoteString($this->normalizeOracleName($dbName));
            $sql = <<<SQL
SELECT table_name
FROM all_tables
WHERE owner = {$ownerValue}
  AND table_name NOT LIKE '\_%' ESCAPE '\'
  AND table_name NOT LIKE 'SQL\_%' ESCAPE '\'
  AND table_name NOT IN (
      'CHECK_PARAM',
      'CHECK_TYPE',
      'DUAL',
      'HM_RUN_T',
      'PG_TYPE_NMAP',
      'SYS_MVIEW'
  )
ORDER BY table_name
SQL;
        }

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        return array_map('current', $result);
    }

    /**
     * 通过 Oracle 兼容序列获取最近一次插入主键。
     *
     * 序列名为空时 Oracle 模式无法推断主键，返回框架约定的空字符串；否则在当前物理连接上
     * 查询序列 CURRVAL，确保取得本会话刚使用的值。
     *
     * @param BaseQuery   $query    当前查询对象，保留 ThinkORM 接口一致性
     * @param string|null $sequence 序列名，可包含 Owner
     *
     * @return mixed 序列当前值；未提供序列名时返回空字符串
     */
    public function getLastInsID(BaseQuery $query, ?string $sequence = null)
    {
        $sequence = $this->getKingbaseInsertSequence();
        if (null === $sequence || '' === $sequence) {
            return '';
        }

        return $this->resolveKingbaseGeneratedKey(function () use ($sequence) {
            $sequence = $this->parseSequenceName($sequence);
            $pdo = $this->linkID->query("SELECT {$sequence}.CURRVAL AS id FROM dual");

            return $pdo->fetchColumn();
        });
    }

    /**
     * 从 Oracle NEXTVAL 或 PostgreSQL 风格 nextval() 默认值中提取真实序列名。
     */
    protected function extractSequenceFromDefault(?string $default): ?string
    {
        if (null === $default || '' === trim($default)) {
            return null;
        }

        if (preg_match(
            '/^\s*((?:"[^"]+"|[A-Za-z_][A-Za-z0-9_$#]*)(?:\.(?:"[^"]+"|[A-Za-z_][A-Za-z0-9_$#]*))?)\s*\.\s*NEXTVAL\b/i',
            $default,
            $match
        )) {
            return str_replace('"', '', $match[1]);
        }

        if (preg_match('/^\s*nextval\s*\(\s*\'((?:\'\'|[^\'])+)\'\s*(?:::[^)]+)?\s*\)/i', $default, $match)) {
            $sequence = str_replace("''", "'", $match[1]);

            return str_replace('"', '', $sequence);
        }

        if (preg_match('/^\s*nextval\s*\(\s*"([^"]+)"\s*\)/i', $default, $match)) {
            $sequence = $match[1];

            return str_replace('"', '', $sequence);
        }

        return null;
    }

    /**
     * 拆分并规范化 Oracle 风格的 Owner 限定表名。
     *
     * @param string $tableName 表名，可使用 owner.table 形式
     *
     * @return array{0:string|null,1:string} Owner 与表名；无 Owner 时首项为 null
     */
    protected function parseOracleTable(string $tableName): array
    {
        $tableName = trim($tableName, '`');

        if (str_contains($tableName, '.')) {
            [$owner, $tableName] = explode('.', $tableName, 2);

            return [
                $this->normalizeOracleName($owner),
                $this->normalizeOracleName($tableName),
            ];
        }

        return [null, $this->normalizeOracleName($tableName)];
    }

    /**
     * 规范化 Oracle 对象名的大小写。
     *
     * 双引号包围的名称保留原大小写并移除外层引号，未加引号的名称转换为大写。
     *
     * @param string $name 原始对象名
     *
     * @return string 规范化后的对象名
     */
    protected function normalizeOracleName(string $name): string
    {
        $name = trim($name);

        if (str_starts_with($name, '"') && str_ends_with($name, '"')) {
            return substr($name, 1, -1);
        }

        return strtoupper(trim($name, '"'));
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
     * 返回本连接器要求的 KingbaseES 兼容模式。
     *
     * @return string 固定返回 oracle
     */
    protected function getExpectedKingbaseMode(): string
    {
        return 'oracle';
    }

    /**
     * 解析并引用 Oracle 风格序列名。
     *
     * 对 Owner 和序列名称分别规范化并使用双引号引用，以支持限定名称及需要保留
     * 大小写的序列对象。
     *
     * @param string $sequence 序列名，可使用 owner.sequence 形式
     *
     * @return string 可安全拼入 SQL 的序列标识符
     */
    protected function parseSequenceName(string $sequence): string
    {
        $parts = explode('.', $sequence);

        foreach ($parts as &$part) {
            $part = $this->normalizeOracleName($part);
            $part = '"' . str_replace('"', '""', $part) . '"';
        }

        return implode('.', $parts);
    }
}
