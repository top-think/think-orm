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
use PDOStatement;
use think\db\PDOConnection;

/**
 * Sqlite数据库驱动.
 */
class Sqlite extends PDOConnection
{
    /**
     * 解析pdo连接的dsn信息.
     *
     * @param array $config 连接信息
     *
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        return 'sqlite:' . $config['database'];
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

        $sql    = 'PRAGMA table_info( \'' . $tableName . '\' )';
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        if (!empty($result)) {
            foreach ($result as $key => $val) {
                $val = array_change_key_case($val);

                $info[$val['name']] = [
                    'name'    => $val['name'],
                    'type'    => $val['type'],
                    'notnull' => 1 === $val['notnull'],
                    'default' => $val['dflt_value'],
                    'primary' => '1' == $val['pk'],
                    'autoinc' => '1' == $val['pk'],
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
        $sql = "SELECT name FROM sqlite_master WHERE type='table' "
            . 'UNION ALL SELECT name FROM sqlite_temp_master '
            . "WHERE type='table' ORDER BY name";

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

    protected function createPdo($dsn, $username, $password, $params)
    {
        return new SqliteTypedPDO($dsn, $username, $password, $params);
    }

}
/**
 * 查询结果集中的数据类型可以自动转换成对应的PHP类型
 */
class SqliteTypedPDO extends PDO
{
    function __construct($dsn, $username = null, $password = null, $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
    }
    function prepare($query, $options = []) //: PDOStatement|bool
    {
        $stmt = parent::prepare($query, $options);
        if ($stmt!=false && !($stmt instanceof SqlitePDOStatement)) {
            return new SqlitePDOStatement($stmt);
        }
        return stmt;
    }
    function query($query, $fetchMode = null, $classname = null, $constructorArgs = null) //: PDOStatement|bool
    {
        if($fetchMode==PDO::FETCH_COLUMN || $fetchMode==PDO::FETCH_INTO){
			$stmt = parent::query($query, $fetchMode, $classname);
		}elseif($fetchMode==PDO::FETCH_CLASS){
			$stmt = parent::query($query, $fetchMode, $classname, $constructorArgs);
		}elseif($fetchMode==null){
			$stmt = parent::query($query);
		}		
        if ($stmt!=false && !($stmt instanceof SqlitePDOStatement)) {
            return new SqlitePDOStatement($stmt);
        }

        return $stmt;
    }
}

class SqlitePDOStatement extends PDOStatement
{
    private PDOStatement $stmt;
    private $fetchMode;
    function __construct($stmt)
    {
        $this->stmt = $stmt;
        //readonly
        // $this->queryString = $stmt->queryString;
    }
    /**
     * @var string
     */
    // public $queryString;

    function bindColumn($column, &$var, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = null): bool
    {
        return $this->stmt->bindColumn($column, $var, $type, $maxLength, $driverOptions);
    }

    function bindParam($param, &$var, $type = PDO::PARAM_STR, $maxLength = 0, $driverOptions = null): bool
    {
        return $this->stmt->bindParam($param, $var, $type, $maxLength, $driverOptions);
    }

    function bindValue($param, $value, $type = PDO::PARAM_STR): bool
    {
        return $this->stmt->bindValue($param, $value, $type);
    }

    function closeCursor(): bool
    {
        return $this->stmt->closeCursor();
    }

    function columnCount(): int
    {
        return $this->stmt->columnCount();
    }

    function debugDumpParams() //: bool|null
    {
        return $this->stmt->debugDumpParams();
    }

    function errorCode() //: string|null
    {
        return $this->stmt->errorCode();
    }

    function errorInfo(): array
    {
        return $this->stmt->errorInfo();
    }

    function execute($params = null): bool
    {
        return $this->stmt->execute($params);
    }

    function fetch($mode = PDO::FETCH_ASSOC, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0): mixed
    {
        $result = $this->stmt->fetch($mode, $cursorOrientation, $cursorOffset);
        $columnCount = $this->stmt->columnCount();
        if ($this->fetchMode == PDO::FETCH_ASSOC || $mode == PDO::FETCH_ASSOC) { //TODO:暂时支持一种
            for ($i = 0; $i < $columnCount; $i++) {
                $meta = $this->stmt->getColumnMeta($i);
                if ($meta['native_type'] == 'integer' || (!empty($meta['sqlite:decl_type']) && $meta['sqlite:decl_type'] == 'INTEGER')) {
                    $result[$meta['name']] = intval($result[$meta['name']]);
                }
            }
        }
        return $result;
    }


    function fetchAll($mode = PDO::FETCH_ASSOC, $class = null, $constructorArgs = null): array
    {
        if ($mode == PDO::FETCH_COLUMN || $mode == PDO::FETCH_FUNC) {
            $result = $this->stmt->fetchAll($mode, $class);
        } elseif ($mode == PDO::FETCH_CLASS) {
            $result = $this->stmt->fetchAll($mode, $class, $constructorArgs);
        } else {
            $result = $this->stmt->fetchAll($mode);
        }
        // $tableName = null;
        // $pattern = '/\bSELECT\b.*?\bFROM\b\s+["`\'`]?(\w+)["`\'`]?/i';
        // if (preg_match($pattern, trim($this->stmt->queryString), $matches)) {
        //     // 匹配成功，$matches[1] 包含提取出的表名
        //     $tableName = $matches[1];
        // }
        // var_dump($this->stmt->queryString);
        // var_dump($result);
        // if(!empty($tableName)){
        //     // die;
        // }

        if ($this->fetchMode == PDO::FETCH_ASSOC || $mode == PDO::FETCH_ASSOC) { //TODO:暂时支持一种
            $columnCount = $this->stmt->columnCount();
            for ($i = 0; $i < $columnCount; $i++) {
                $meta = $this->stmt->getColumnMeta($i);
                if ($meta['native_type'] == 'integer' || (!empty($meta['sqlite:decl_type']) && $meta['sqlite:decl_type'] == 'INTEGER')) {
                    foreach ($result as &$r) {
                        $r[$meta['name']] = intval($r[$meta['name']]);
                    }
                }
            }
        }
        // echo("===================\n");
        return $result;
    }

    function fetchColumn($column = 0)
    {
        $result = $this->stmt->fetchColumn($column);
        if ($result === false)
            return $result;
        $meta = $this->stmt->getColumnMeta(0);
        if ($meta['native_type'] == 'integer' || (!empty($meta['sqlite:decl_type']) && $meta['sqlite:decl_type'] == 'INTEGER')) {
            $result = intval($result);
        }
        return $result;
    }

    function fetchObject($class = "stdClass", $constructorArgs = []) //: bool|object
    {
        return $this->stmt->fetchObject($class, $constructorArgs);
    }

    function getAttribute($name): mixed
    {
        return $this->stmt->getAttribute($name);
    }


    function getColumnMeta($column) //: array|bool
    {
        return $this->stmt->getColumnMeta($column);
    }

    function getIterator(): \Iterator
    {
        return $this->stmt->getIterator();
    }

    function nextRowset(): bool
    {
        return $this->stmt->nextRowset();
    }

    function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    function setAttribute($attribute, $value): bool
    {
        return $this->stmt->setAttribute($attribute, $value);
    }


    function setFetchMode($mode = null, $class = null, $constructorArgs = null): bool
    {
        $this->fetchMode = $mode;
        return $this->stmt->setFetchMode($mode, $class, $constructorArgs);
    }
}
