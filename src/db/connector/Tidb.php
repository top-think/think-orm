<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2020 http://thinkphp.tech All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Bison 'goldeagle' Fan <bison@bitseed.cn>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\db\connector;

use PDO;
use think\db\PDOConnection;

/**
 * tidb 数据库驱动
 */
class Tidb extends PDOConnection
{

    /**
     * 解析pdo连接的dsn信息, tidb can use mysql dsn info.
     * @access protected
     * @param  array $config 连接信息
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        if (!empty($config['socket'])) {
            $dsn = 'mysql:unix_socket=' . $config['socket'];
        } elseif (!empty($config['hostport'])) {
            $dsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'];
        } else {
            $dsn = 'mysql:host=' . $config['hostname'];
        }
        $dsn .= ';dbname=' . $config['database'];

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param  string $tableName
     * @return array
     */
    public function getFields(string $tableName): array
    {
        [$tableName] = explode(' ', $tableName);

        if (false === strpos($tableName, '`')) {
            if (strpos($tableName, '.')) {
                $tableName = str_replace('.', '`.`', $tableName);
            }
            $tableName = '`' . $tableName . '`';
        }

        $sql    = 'SHOW FULL COLUMNS FROM ' . $tableName;
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => 'NO' == $val['null'],
                    'default' => $val['default'],
                    'primary' => strtolower($val['key']) == 'pri',
                    'autoinc' => strtolower($val['extra']) == 'auto_increment',
                    'comment' => $val['comment'],
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息
     * @access public
     * @param  string $dbName
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql    = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    public function getExplain(){ return ''; }
}
