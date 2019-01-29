<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\db;

use Exception;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Query as MongoQuery;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use think\db\connector\Mongo as MongoConnection;
use think\db\Query;

class Mongo extends Query
{

    /**
     * 架构函数
     * @access public
     */
    public function __construct(MongoConnection $connection = null)
    {
        if (is_null($connection)) {
            $this->connection = MongoConnection::instance();
        } else {
            $this->connection = $connection;
        }

        $this->prefix = $this->connection->getConfig('prefix');
    }

    /**
     * 去除某个查询条件
     * @access public
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function removeWhereField($field, $logic = 'and')
    {
        $logic = '$' . strtoupper($logic);

        if (isset($this->options['where'][$logic])) {
            foreach ($this->options['where'][$logic] as $key => $val) {
                if (is_array($val) && $val[0] == $field) {
                    unset($this->options['where'][$logic][$key]);
                }
            }
        }

        return $this;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string $namespace
     * @param MongoQuery        $query 查询对象
     * @param ReadPreference    $readPreference readPreference
     * @param bool|string       $class 指定返回的数据集对象
     * @param string|array      $typeMap 指定返回的typeMap
     * @return mixed
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function mongoQuery($namespace, MongoQuery $query, ReadPreference $readPreference = null, $class = false, $typeMap = null)
    {
        return $this->connection->query($namespace, $query, $readPreference, $class, $typeMap);
    }

    /**
     * 执行指令 返回数据集
     * @access public
     * @param Command           $command 指令
     * @param string            $dbName
     * @param ReadPreference    $readPreference readPreference
     * @param bool|string       $class 指定返回的数据集对象
     * @param string|array      $typeMap 指定返回的typeMap
     * @return mixed
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function command(Command $command, $dbName = '', ReadPreference $readPreference = null, $class = false, $typeMap = null)
    {
        return $this->connection->command($command, $dbName, $readPreference, $class, $typeMap);
    }

    /**
     * 执行语句
     * @access public
     * @param string        $namespace
     * @param BulkWrite     $bulk
     * @param WriteConcern  $writeConcern
     * @return int
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function mongoExecute($namespace, BulkWrite $bulk, WriteConcern $writeConcern = null)
    {
        return $this->connection->execute($namespace, $bulk, $writeConcern);
    }

    /**
     * 执行command
     * @access public
     * @param string|array|object   $command 指令
     * @param mixed                 $extra 额外参数
     * @param string                $db 数据库名
     * @return array
     */
    public function cmd($command, $extra = null, $db = null)
    {
        return $this->connection->cmd($this, $command, $extra, $db);
    }

    /**
     * 指定distinct查询
     * @access public
     * @param string $field 字段名
     * @return array
     */
    public function distinct($field)
    {
        $result = $this->cmd('distinct', $field);
        return $result[0]['values'];
    }

    /**
     * 获取数据库的所有collection
     * @access public
     * @param string  $db 数据库名称 留空为当前数据库
     * @throws Exception
     */
    public function listCollections($db = '')
    {
        $cursor = $this->cmd('listCollections', null, $db);
        $result = [];
        foreach ($cursor as $collection) {
            $result[] = $collection['name'];
        }
        return $result;
    }

    /**
     * COUNT查询
     * @access public
     * @return integer
     */
    public function count($field = null)
    {
        $this->parseOptions();

        $result = $this->cmd('count');

        return $result[0]['n'];
    }

    /**
     * 聚合查询
     * @access public
     * @param string $aggregate 聚合指令
     * @param string $field     字段名
     * @param bool   $force     强制转为数字类型
     * @return mixed
     */
    public function aggregate($aggregate, $field, $force = false)
    {
        $this->parseOptions();

        $result = $this->cmd('aggregate', [strtolower($aggregate), $field]);
        $value  = isset($result[0]['aggregate']) ? $result[0]['aggregate'] : 0;

        if ($force) {
            $value += 0;
        }

        return $value;
    }

    /**
     * 多聚合操作
     *
     * @param array $aggregate 聚合指令, 可以聚合多个参数, 如 ['sum' => 'field1', 'avg' => 'field2']
     * @param array $groupBy 类似mysql里面的group字段, 可以传入多个字段, 如 ['field_a', 'field_b', 'field_c']
     * @return array 查询结果
     */
    public function multiAggregate($aggregate, $groupBy)
    {
        $this->parseOptions();

        $result = $this->cmd('multiAggregate', [$aggregate, $groupBy]);

        foreach ($result as &$row) {
            if (isset($row['_id']) && !empty($row['_id'])) {
                foreach ($row['_id'] as $k => $v) {
                    $row[$k] = $v;
                }
                unset($row['_id']);
            }
        }

        return $result;
    }

    /**
     * 字段值(延迟)增长
     * @access public
     * @param string    $field 字段名
     * @param integer   $step 增长值
     * @return integer|true
     * @throws Exception
     */
    public function setInc($field, $step = 1)
    {
        return $this->setField($field, ['$inc', $step]);
    }

    /**
     * 字段值（延迟）减少
     * @access public
     * @param string    $field 字段名
     * @param integer   $step 减少值
     * @return integer|true
     * @throws Exception
     */
    public function setDec($field, $step = 1)
    {
        return $this->setField($field, ['$inc', -1 * $step]);
    }

    /**
     * 字段值增长
     * @access public
     * @param string|array $field 字段名
     * @param integer      $step  增长值
     * @return $this
     */
    public function inc($field, $step = 1, $op = 'inc')
    {
        return parent::inc($field, $step, strtolower('$' . $op));
    }

    /**
     * 字段值减少
     * @access public
     * @param string|array $field 字段名
     * @param integer      $step  减少值
     * @return $this
     */
    public function dec($field, $step = 1)
    {
        return $this->inc($field, -1 * $step);
    }

    /**
     * 指定当前操作的collection
     * @access public
     * @param string $collection
     * @return $this
     */
    public function collection($collection)
    {
        return $this->table($collection);
    }

    /**
     * 不主动获取数据集
     * @access public
     * @param bool $cursor 是否返回 Cursor 对象
     * @return $this
     */
    public function fetchCursor($cursor = true)
    {
        $this->options['fetch_cursor'] = $cursor;
        return $this;
    }

    /**
     * 设置typeMap
     * @access public
     * @param string|array $typeMap
     * @return $this
     */
    public function typeMap($typeMap)
    {
        $this->options['typeMap'] = $typeMap;
        return $this;
    }

    /**
     * awaitData
     * @access public
     * @param bool $awaitData
     * @return $this
     */
    public function awaitData($awaitData)
    {
        $this->options['awaitData'] = $awaitData;
        return $this;
    }

    /**
     * batchSize
     * @access public
     * @param integer $batchSize
     * @return $this
     */
    public function batchSize($batchSize)
    {
        $this->options['batchSize'] = $batchSize;
        return $this;
    }

    /**
     * exhaust
     * @access public
     * @param bool $exhaust
     * @return $this
     */
    public function exhaust($exhaust)
    {
        $this->options['exhaust'] = $exhaust;
        return $this;
    }

    /**
     * 设置modifiers
     * @access public
     * @param array $modifiers
     * @return $this
     */
    public function modifiers($modifiers)
    {
        $this->options['modifiers'] = $modifiers;
        return $this;
    }

    /**
     * 设置noCursorTimeout
     * @access public
     * @param bool $noCursorTimeout
     * @return $this
     */
    public function noCursorTimeout($noCursorTimeout)
    {
        $this->options['noCursorTimeout'] = $noCursorTimeout;
        return $this;
    }

    /**
     * 设置oplogReplay
     * @access public
     * @param bool $oplogReplay
     * @return $this
     */
    public function oplogReplay($oplogReplay)
    {
        $this->options['oplogReplay'] = $oplogReplay;
        return $this;
    }

    /**
     * 设置partial
     * @access public
     * @param bool $partial
     * @return $this
     */
    public function partial($partial)
    {
        $this->options['partial'] = $partial;
        return $this;
    }

    /**
     * maxTimeMS
     * @access public
     * @param string $maxTimeMS
     * @return $this
     */
    public function maxTimeMS($maxTimeMS)
    {
        $this->options['maxTimeMS'] = $maxTimeMS;
        return $this;
    }

    /**
     * collation
     * @access public
     * @param array $collation
     * @return $this
     */
    public function collation($collation)
    {
        $this->options['collation'] = $collation;
        return $this;
    }

    /**
     * 设置返回字段
     * @access public
     * @param array     $field
     * @param boolean   $except 是否排除
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '', $alias = '')
    {
        if (empty($field)) {
            return $this;
        } elseif ($field instanceof Expression) {
            $this->options['field'][] = $field;
            return $this;
        }

        if (is_string($field)) {
            if (preg_match('/[\<\'\"\(]/', $field)) {
                return $this->fieldRaw($field);
            }

            $field = array_map('trim', explode(',', $field));
        }

        $projection = [];
        foreach ($field as $key => $val) {
            if (is_numeric($key)) {
                $projection[$val] = $except ? 0 : 1;
            } else {
                $projection[$key] = $val;
            }
        }

        $this->options['projection'] = $projection;

        return $this;
    }

    /**
     * 设置skip
     * @access public
     * @param integer $skip
     * @return $this
     */
    public function skip($skip)
    {
        $this->options['skip'] = intval($skip);
        return $this;
    }

    /**
     * 设置slaveOk
     * @access public
     * @param bool $slaveOk
     * @return $this
     */
    public function slaveOk($slaveOk)
    {
        $this->options['slaveOk'] = $slaveOk;
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return $this
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length)) {
            if (is_numeric($offset)) {
                $length = $offset;
                $offset = 0;
            } else {
                list($offset, $length) = explode(',', $offset);
            }
        }
        $this->options['skip']  = intval($offset);
        $this->options['limit'] = intval($length);

        return $this;
    }

    /**
     * 设置sort
     * @access public
     * @param array|string|object   $field
     * @param string                $order
     * @return $this
     */
    public function order($field, $order = '')
    {
        if (is_array($field)) {
            $this->options['sort'] = $field;
        } else {
            $this->options['sort'][$field] = 'asc' == strtolower($order) ? 1 : -1;
        }
        return $this;
    }

    /**
     * 设置tailable
     * @access public
     * @param bool $tailable
     * @return $this
     */
    public function tailable($tailable)
    {
        $this->options['tailable'] = $tailable;
        return $this;
    }

    /**
     * 设置writeConcern对象
     * @access public
     * @param WriteConcern $writeConcern
     * @return $this
     */
    public function writeConcern($writeConcern)
    {
        $this->options['writeConcern'] = $writeConcern;
        return $this;
    }

    /**
     * 把主键值转换为查询条件 支持复合主键
     * @access public
     * @param array|string  $data 主键数据
     * @param mixed         $options 表达式参数
     * @return void
     * @throws Exception
     */
    public function parsePkWhere($data)
    {
        $pk = $this->getPk();

        if (is_string($pk)) {
            // 根据主键查询
            if (is_array($data)) {
                $where[$pk] = isset($data[$pk]) ? [$pk, '=', $data[$pk]] : [$pk, 'in', $data];
            } else {
                $where[$pk] = strpos($data, ',') ? [$pk, 'IN', $data] : [$pk, '=', $data];
            }
        }

        if (!empty($where)) {
            if (isset($this->options['where']['$and'])) {
                $this->options['where']['$and'] = array_merge($this->options['where']['$and'], $where);
            } else {
                $this->options['where']['$and'] = $where;
            }
        }

        return;
    }

    /**
     * 获取当前数据表的主键
     * @access public
     * @param string|array $options 数据表名或者查询参数
     * @return string|array
     */
    public function getPk($options = '')
    {
        return $this->pk ?: $this->connection->getConfig('pk');
    }

    /**
     * 执行查询但只返回Cursor对象
     * @access public
     * @return Cursor
     */
    public function getCursor()
    {
        $this->parseOptions();

        return $this->connection->getCursor($this);
    }

    /**
     * 获取模型的更新条件
     * @access protected
     * @param  array $options 查询参数
     */
    protected function getModelUpdateCondition(array $options)
    {
        return isset($options['where']['$and']) ? $options['where']['$and'] : null;
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access protected
     * @return array
     */
    protected function parseOptions()
    {
        $options = $this->options;

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }

        foreach (['where', 'data'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = [];
            }
        }

        $modifiers = empty($options['modifiers']) ? [] : $options['modifiers'];
        if (isset($options['comment'])) {
            $modifiers['$comment'] = $options['comment'];
        }

        if (isset($options['maxTimeMS'])) {
            $modifiers['$maxTimeMS'] = $options['maxTimeMS'];
        }

        if (!empty($modifiers)) {
            $options['modifiers'] = $modifiers;
        }

        if (!isset($options['projection']) || '*' == $options['projection']) {
            $options['projection'] = [];
        }

        if (!isset($options['typeMap'])) {
            $options['typeMap'] = $this->getConfig('type_map');
        }

        if (!isset($options['limit'])) {
            $options['limit'] = 0;
        }

        foreach (['master', 'fetch_cursor'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page                  = $page > 0 ? $page : 1;
            $listRows              = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset                = $listRows * ($page - 1);
            $options['skip']       = intval($offset);
            $options['limit']      = intval($listRows);
        }

        $this->options = $options;

        return $options;
    }

}
