<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\db\connector;

use PDO;
use think\db\BaseQuery;
use think\db\PDOConnection;

/**
 * Pgsql数据库驱动.
 */
class Pgsql extends PDOConnection
{
    /**
     * 默认PDO连接参数.
     *
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    /**
     * 解析pdo连接的dsn信息.
     *
     * @param array $config 连接信息
     *
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        $dsn = 'pgsql:dbname=' . $config['database'] . ';host=' . $config['hostname'];

        if (!empty($config['hostport'])) {
            $dsn .= ';port=' . $config['hostport'];
        }

        return $dsn;
    }

    /**
     * 取得数据表的字段信息.
     *
     * @param string $tableName
     *
     * @return array
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);

        $sql    = 'select fields_name as "field",fields_type as "type",fields_not_null as "null",fields_key_name as "key",fields_default as "default",fields_default as "extra",fields_comment as "comment" from table_msg(\'' . $tableName . '\');';
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ('' !== $val['null']),
                    'default' => $val['default'],
                    'primary' => !empty($val['key']),
                    'autoinc' => str_starts_with((string) $val['extra'], 'nextval('),
                    'comment' => $val['comment'],
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息.
     *
     * @param string $dbName
     *
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql    = "select tablename as Tables_in_test from pg_tables where  schemaname ='public'";
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }

    protected function getFieldType(string $type): string
    {
        // 将字段类型转换为小写以进行比较
        $type = strtolower($type);

        return match (true) {
            str_starts_with($type, 'set') => 'set',
            str_starts_with($type, 'enum') => 'enum',
            str_starts_with($type, 'bigint'),
            str_contains($type, 'numeric') => 'bigint',
            str_contains($type, 'float') || str_contains($type, 'double') ||
            str_contains($type, 'decimal') || str_contains($type, 'real') ||
            str_contains($type, 'int') || str_contains($type, 'serial') ||
            str_contains($type, 'bit') => 'int',
            str_contains($type, 'bool') => 'bool',
            str_starts_with($type, 'timestamp') => 'timestamp',
            str_starts_with($type, 'datetime') => 'datetime',
            str_starts_with($type, 'date') => 'date',
            default => 'string',
        };
    }

    public function insert(BaseQuery $query, bool $getLastInsID = false)
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        // 生成SQL语句
        $sql = $this->builder->insert($query);

        // 执行操作
        $result = '' == $sql ? 0 : $this->pdoExecute($query, $sql);

        if ($result) {
            // todo 应该改造为使用 returning 返回完全解决该问题
            $sequence  = $options['sequence'] ?? null;
            $lastInsId = $getLastInsID ? $this->getLastInsID($query, $sequence) : null;

            $data = $options['data'];

            if ($lastInsId) {
                $pk = $query->getAutoInc();
                if ($pk && is_string($pk)) {
                    $data[$pk] = $lastInsId;
                }
            }

            $query->setOption('data', $data);

            $this->db->trigger('after_insert', $query);

            if ($getLastInsID && $lastInsId) {
                return $lastInsId;
            }
        }

        return $result;
    }

    public function getLastInsID(BaseQuery $query, ?string $sequence = null)
    {
        $insertId = $this->linkID->lastInsertId($sequence);

        return $this->autoInsIDType($query, $insertId);
    }
}
