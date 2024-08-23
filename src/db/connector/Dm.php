<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\db\connector;

use PDO;
use think\db\exception\PDOException;
use think\db\PDOConnection;

/**
 * mysql数据库驱动
 */
class Dm extends PDOConnection
{

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param  array $config 连接信息
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        $dsn = sprintf("dm:host=%s;port=%s;dbname=%s", $config['hostname'], $config['hostport'], $config['database']);
        return  $dsn;
    }
    /**
     * 连接数据库方法
     * @access public
     * @param array      $config         连接参数
     * @param integer    $linkNum        连接序号
     * @param array|bool $autoConnection 是否自动连接主数据库（用于分布式）
     * @return PDO
     * @throws PDOException
     */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false): PDO {
        if (empty($config)) {
            $config = $this->config;
        } else {
            $config = array_merge($this->config, $config);
        }
        
        $PDO = parent::connect($config, $linkNum, $autoConnection);

        $PDO->query(sprintf("SET SCHEMA  %s", $config['database']));
        return $PDO;

    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param  string $tableName
     * @return array
     */
    public function getFields(string $tableName): array
    {
        $tableName = str_replace("`", "", $tableName);

        $sql = $sql = sprintf("
select a.column_name,data_type,decode(nullable,'Y',0,1) notnull,data_default,decode(a.column_name,b.column_name,1,0) pk from user_tab_columns a,(select column_name from user_constraints c,user_cons_columns col where c.constraint_name=col.constraint_name and c.constraint_type='P'and c.table_name='%s') b where table_name='%s' and a.column_name=b.column_name(+)
", $tableName, $tableName);

        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info = [];

        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['column_name']] = [
                    'name' => $val['column_name'],
                    'type' => $val['data_type'],
                    'notnull' => 1 == $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'] == 1,
                    'autoinc' => $val['pk'] == 1,
                    'comment' => '',
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
        $sql = " SELECT table_name FROM USER_TABLES  where TABLESPACE_NAME='MAIN'";
        $pdo = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }

    /**
     * 启动XA事务
     * @access public
     * @param  string $xid XA事务id
     * @return void
     */
    public function startTransXa(string $xid): void
    {
        $this->initConnect(true);
        $this->linkID->exec("XA START '$xid'");
    }

}
