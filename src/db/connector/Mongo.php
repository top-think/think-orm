<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);
namespace think\db\connector;

use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query as MongoQuery;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use think\Cache;
use think\Collection;
use think\Db;
use think\db\builder\Mongo as Builder;
use think\db\Mongo as Query;
use think\Exception;

/**
 * Mongo数据库驱动
 */
class Mongo
{
    protected $dbName = ''; // dbName
    /** @var string 当前SQL指令 */
    protected $queryStr = '';
    // 查询数据类型
    protected $typeMap = 'array';
    protected $mongo; // MongoDb Object
    protected $cursor; // MongoCursor Object

    /** @var Manager[] 数据库连接ID 支持多个连接 */
    protected $links = [];
    /** @var Manger 当前连接ID */
    protected $linkID;
    protected $linkRead;
    protected $linkWrite;
    // Builder对象
    protected $builder;
    // 返回或者影响记录数
    protected $numRows = 0;
    // 错误信息
    protected $error = '';
    // 查询参数
    protected $options = [];
    // 数据库连接参数配置
    protected $config = [
        // 数据库类型
        'type'            => '',
        // 服务器地址
        'hostname'        => '',
        // 数据库名
        'database'        => '',
        // 是否是复制集
        'is_replica_set'  => false,
        // 用户名
        'username'        => '',
        // 密码
        'password'        => '',
        // 端口
        'hostport'        => '',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => 'utf8',
        // 主键名
        'pk'              => '_id',
        // 主键类型
        'pk_type'         => 'ObjectID',
        // 数据库表前缀
        'prefix'          => '',
        // 数据库调试模式
        'debug'           => false,
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 自动写入时间戳字段
        'auto_timestamp'  => false,
        // 时间字段取出后的默认时间格式
        'datetime_format' => 'Y-m-d H:i:s',
        // 是否_id转换为id
        'pk_convert_id'   => false,
        // typeMap
        'type_map'        => ['root' => 'array', 'document' => 'array'],
        // Query对象
        'query'           => '\\think\\db\\Mongo',
    ];

    /**
     * 缓存对象
     * @var Cache
     */
    protected $cache;

    /**
     * Db对象
     * @var Db
     */
    protected $db;

    /**
     * 日志记录
     * @var array
     */
    protected $log = [];

    /**
     * 架构函数 读取数据库配置信息
     * @access public
     * @param Cache $cache 缓存对象
     * @param Log   $log 日志对象
     * @param array $config 数据库配置数组
     */
    public function __construct(Cache $cache, array $config = [])
    {
        if (!class_exists('\MongoDB\Driver\Manager')) {
            throw new Exception('require mongodb > 1.0');
        }

        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $this->builder = new Builder($this);

        $this->cache = $cache;
    }

    /**
     * 获取当前连接器类对应的Query类
     * @access public
     * @return string
     */
    public function getQueryClass(): string
    {
        return $this->getConfig('query') ?: Query::class;
    }

    /**
     * 设置当前的数据库Builder对象
     * @access protected
     * @param  Builder $builder
     * @return void
     */
    protected function setBuilder(Builder $builder): void
    {
        $this->builder = $builder;
    }

    /**
     * 获取当前的builder实例对象
     * @access public
     * @return Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * 设置当前的数据库Db对象
     * @access public
     * @param Db $db
     * @return void
     */
    public function setDb(Db $db): void
    {
        $this->db = $db;
    }

    /**
     * 连接数据库方法
     * @access public
     * @param  array   $config 连接参数
     * @param  integer $linkNum 连接序号
     * @return Manager
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public function connect(array $config = [], int $linkNum = 0): Manager
    {
        if (!isset($this->links[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            } else {
                $config = array_merge($this->config, $config);
            }

            $this->dbName  = $config['database'];
            $this->typeMap = $config['type_map'];

            if ($config['pk_convert_id'] && '_id' == $config['pk']) {
                $this->config['pk'] = 'id';
            }

            if (empty($config['dsn'])) {
                $config['dsn'] = 'mongodb://' . ($config['username'] ? "{$config['username']}" : '') . ($config['password'] ? ":{$config['password']}@" : '') . $config['hostname'] . ($config['hostport'] ? ":{$config['hostport']}" : '');
            }

            if ($config['debug']) {
                $startTime = microtime(true);
            }

            $this->links[$linkNum] = new Manager($config['dsn'], $config['params']);

            // 记录数据库连接信息
            $this->logger('[ MongoDb ] CONNECT :[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $config['dsn']);

        }

        return $this->links[$linkNum];
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param  string $config 配置名称
     * @return mixed
     */
    public function getConfig(string $config = '')
    {
        if ('' === $config) {
            return $this->config;
        }

        return $this->config[$config] ?? null;
    }

    /**
     * 设置数据库的配置参数
     * @access public
     * @param  array $config 配置
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取Mongo Manager对象
     * @access public
     * @return Manager|null
     */
    public function getMongo()
    {
        return $this->mongo ?: null;
    }

    /**
     * 设置/获取当前操作的database
     * @access public
     * @param  string  $db db
     * @throws Exception
     */
    public function db(string $db = null)
    {
        if (is_null($db)) {
            return $this->dbName;
        } else {
            $this->dbName = $db;
        }
    }

    /**
     * 执行查询但只返回Cursor对象
     * @access public
     * @param  Query $query 查询对象
     * @return Cursor
     */
    public function getCursor(Query $query): Cursor
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        // 生成MongoQuery对象
        $mongoQuery = $this->builder->select($query);

        // 执行查询操作
        return $this->cursor($options['table'], $mongoQuery, $options['readPreference'] ?? null);
    }

    /**
     * 执行查询并返回Cursor对象
     * @access public
     * @param string         $namespace 当前查询的collection
     * @param MongoQuery     $query 查询对象
     * @param ReadPreference $readPreference readPreference
     * @return Cursor
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function cursor(string $namespace, MongoQuery $query, ReadPreference $readPreference = null): Cursor
    {
        $this->initConnect(false);
        $this->db->updateQueryTimes();

        if (false === strpos($namespace, '.')) {
            $namespace = $this->dbName . '.' . $namespace;
        }

        if ($this->config['debug'] && !empty($this->queryStr)) {
            // 记录执行指令
            $this->queryStr = 'db' . strstr($namespace, '.') . '.' . $this->queryStr;
        }

        $this->debug(true);

        $this->cursor = $this->mongo->executeQuery($namespace, $query, $readPreference);

        $this->debug(false);

        return $this->cursor;
    }

    /**
     * 执行查询
     * @access public
     * @param  string         $namespace 当前查询的collection
     * @param  MongoQuery     $query 查询对象
     * @param  ReadPreference $readPreference readPreference
     * @param  string|array   $typeMap 指定返回的typeMap
     * @return array
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function query(string $namespace, MongoQuery $query, ReadPreference $readPreference = null, $typeMap = null): array
    {
        $this->cursor($namespace, $query, $readPreference);

        return $this->getResult($typeMap);
    }

    /**
     * 执行写操作
     * @access public
     * @param string        $namespace
     * @param BulkWrite     $bulk
     * @param WriteConcern  $writeConcern
     *
     * @return WriteResult
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function execute(string $namespace, BulkWrite $bulk, WriteConcern $writeConcern = null)
    {
        $this->initConnect(true);
        $this->db->updateQueryTimes();

        if (false === strpos($namespace, '.')) {
            $namespace = $this->dbName . '.' . $namespace;
        }

        if ($this->config['debug'] && !empty($this->queryStr)) {
            // 记录执行指令
            $this->queryStr = 'db' . strstr($namespace, '.') . '.' . $this->queryStr;
        }

        $this->debug(true);

        $writeResult = $this->mongo->executeBulkWrite($namespace, $bulk, $writeConcern);

        $this->debug(false);

        $this->numRows = $writeResult->getMatchedCount();

        return $writeResult;
    }

    /**
     * 执行指令
     * @access public
     * @param  Command        $command 指令
     * @param  string         $dbName 当前数据库名
     * @param  ReadPreference $readPreference readPreference
     * @param  string|array   $typeMap 指定返回的typeMap
     * @return array
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function command(Command $command, string $dbName = '', ReadPreference $readPreference = null, $typeMap = null): array
    {
        $this->initConnect(false);
        $this->db->updateQueryTimes();

        $this->debug(true);

        $dbName = $dbName ?: $this->dbName;

        if ($this->config['debug'] && !empty($this->queryStr)) {
            $this->queryStr = 'db.' . $this->queryStr;
        }

        $this->cursor = $this->mongo->executeCommand($dbName, $command, $readPreference);

        $this->debug(false);

        return $this->getResult($typeMap);
    }

    /**
     * 获得数据集
     * @access protected
     * @param  string|array      $typeMap 指定返回的typeMap
     * @return mixed
     */
    protected function getResult($typeMap = null): array
    {
        // 设置结果数据类型
        if (is_null($typeMap)) {
            $typeMap = $this->typeMap;
        }

        $typeMap = is_string($typeMap) ? ['root' => $typeMap] : $typeMap;

        $this->cursor->setTypeMap($typeMap);

        // 获取数据集
        $result = $this->cursor->toArray();

        if ($this->getConfig('pk_convert_id')) {
            // 转换ObjectID 字段
            foreach ($result as &$data) {
                $this->convertObjectID($data);
            }
        }

        $this->numRows = count($result);

        return $result;
    }

    /**
     * ObjectID处理
     * @access protected
     * @param  array $data 数据
     * @return void
     */
    protected function convertObjectID(array &$data): void
    {
        if (isset($data['_id']) && is_object($data['_id'])) {
            $data['id'] = $data['_id']->__toString();
            unset($data['_id']);
        }
    }

    /**
     * 数据库日志记录（仅供参考）
     * @access public
     * @param  string $type 类型
     * @param  mixed  $data 数据
     * @param  array  $options 参数
     * @return void
     */
    public function log(string $type, $data, array $options = [])
    {
        if (!$this->config['debug']) {
            return;
        }

        if (is_array($data)) {
            array_walk_recursive($data, function (&$value) {
                if ($value instanceof ObjectID) {
                    $value = $value->__toString();
                }
            });
        }

        switch (strtolower($type)) {
            case 'aggregate':
                $this->queryStr = 'runCommand(' . ($data ? json_encode($data) : '') . ');';
                break;
            case 'find':
                $this->queryStr = $type . '(' . ($data ? json_encode($data) : '') . ')';

                if (isset($options['sort'])) {
                    $this->queryStr .= '.sort(' . json_encode($options['sort']) . ')';
                }

                if (isset($options['limit'])) {
                    $this->queryStr .= '.limit(' . $options['limit'] . ')';
                }

                $this->queryStr .= ';';
                break;
            case 'insert':
            case 'remove':
                $this->queryStr = $type . '(' . ($data ? json_encode($data) : '') . ');';
                break;
            case 'update':
                $this->queryStr = $type . '(' . json_encode($options) . ',' . json_encode($data) . ');';
                break;
            case 'cmd':
                $this->queryStr = $data . '(' . json_encode($options) . ');';
                break;
        }

        $this->options = $options;
    }

    /**
     * 获取最近执行的指令
     * @access public
     * @return string
     */
    public function getLastSql(): string
    {
        return $this->queryStr;
    }

    /**
     * 触发SQL事件
     * @access protected
     * @param  string $sql SQL语句
     * @param  string $runtime SQL运行时间
     * @param  mixed  $options 参数
     * @param  bool   $master  主从标记
     * @return void
     */
    protected function triggerSql(string $sql, string $runtime, array $options = [], bool $master = false): void
    {
        $listen = $this->db->getListen();

        if (!empty($listen)) {
            foreach ($listen as $callback) {
                if (is_callable($callback)) {
                    $callback($sql, $runtime, $options, $master);
                }
            }
        } else {
            // 未注册监听则记录到日志中
            if ($this->config['deploy']) {
                // 分布式记录当前操作的主从
                $master = $master ? 'master|' : 'slave|';
            } else {
                $master = '';
            }
            $this->logger('[ SQL ] ' . $sql . ' [' . $master . ' RunTime:' . $runtime . 's ]');
        }
    }

    public function logger(string $log): void
    {
        $this->config['debug'] && $this->log[] = $log;
    }

    public function getSqlLog(): array
    {
        return $this->log;
    }

    /**
     * 数据库调试 记录当前SQL及分析性能
     * @access protected
     * @param boolean $start 调试开始标记 true 开始 false 结束
     * @param string  $sql 执行的SQL语句 留空自动获取
     * @param bool    $master  主从标记
     * @return void
     */
    protected function debug(bool $start, string $sql = '', bool $master = false)
    {
        if (!empty($this->config['debug'])) {
            // 开启数据库调试模式
            if ($start) {
                $this->queryStartTime = microtime(true);
            } else {
                // 记录操作结束时间
                $runtime = number_format((microtime(true) - $this->queryStartTime), 6);

                $sql = $sql ?: $this->queryStr;

                // SQL监听
                $this->triggerSql($sql, $runtime, $this->options, $master);
            }
        }
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free()
    {
        $this->cursor = null;
    }

    /**
     * 关闭数据库
     * @access public
     */
    public function close()
    {
        $this->mongo     = null;
        $this->cursor    = null;
        $this->linkRead  = null;
        $this->linkWrite = null;
        $this->links     = [];
    }

    /**
     * 初始化数据库连接
     * @access protected
     * @param boolean $master 是否主服务器
     * @return void
     */
    protected function initConnect(bool $master = true): void
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            if ($master) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }

                $this->mongo = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }

                $this->mongo = $this->linkRead;
            }
        } elseif (!$this->mongo) {
            // 默认单数据库
            $this->mongo = $this->connect();
        }
    }

    /**
     * 连接分布式服务器
     * @access protected
     * @param  boolean $master 主服务器
     * @return Manager
     */
    protected function multiConnect(bool $master = false): Manager
    {
        $config = [];
        // 分布式数据库配置解析
        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn'] as $name) {
            $config[$name] = is_string($this->config[$name]) ? explode(',', $this->config[$name]) : $this->config[$name];
        }

        // 主服务器序号
        $m = floor(mt_rand(0, $this->config['master_num'] - 1));

        if ($this->config['rw_separate']) {
            // 主从式采用读写分离
            if ($master) // 主服务器写入
            {
                if ($this->config['is_replica_set']) {
                    return $this->replicaSetConnect();
                } else {
                    $r = $m;
                }
            } elseif (is_numeric($this->config['slave_no'])) {
                // 指定服务器读
                $r = $this->config['slave_no'];
            } else {
                // 读操作连接从服务器 每次随机连接的数据库
                $r = floor(mt_rand($this->config['master_num'], count($config['hostname']) - 1));
            }
        } else {
            // 读写操作不区分服务器 每次随机连接的数据库
            $r = floor(mt_rand(0, count($config['hostname']) - 1));
        }

        $dbConfig = [];

        foreach (['username', 'password', 'hostname', 'hostport', 'database', 'dsn'] as $name) {
            $dbConfig[$name] = $config[$name][$r] ?? $config[$name][0];
        }

        return $this->connect($dbConfig, $r);
    }

    /**
     * 创建基于复制集的连接
     * @return Manager
     */
    public function replicaSetConnect(): Manager
    {
        $this->dbName  = $this->config['database'];
        $this->typeMap = $this->config['type_map'];

        if ($this->config['debug']) {
            $startTime = microtime(true);
        }

        $this->config['params']['replicaSet'] = $this->config['database'];

        $manager = new Manager($this->buildUrl(), $this->config['params']);

        // 记录数据库连接信息
        $this->logger('[ MongoDB ] ReplicaSet CONNECT:[ UseTime:' . number_format(microtime(true) - $startTime, 6) . 's ] ' . $this->config['dsn']);

        return $manager;
    }

    /**
     * 根据配置信息 生成适用于连接复制集的 URL
     * @return string
     */
    private function buildUrl(): string
    {
        $url = 'mongodb://' . ($this->config['username'] ? "{$this->config['username']}" : '') . ($this->config['password'] ? ":{$this->config['password']}@" : '');

        $hostList = is_string($this->config['hostname']) ? explode(',', $this->config['hostname']) : $this->config['hostname'];
        $portList = is_string($this->config['hostport']) ? explode(',', $this->config['hostport']) : $this->config['hostport'];

        for ($i = 0; $i < count($hostList); $i++) {
            $url = $url . $hostList[$i] . ':' . $portList[0] . ',';
        }

        return rtrim($url, ",") . '/';
    }

    /**
     * 插入记录
     * @access public
     * @param  Query     $query 查询对象
     * @param  boolean   $getLastInsID 返回自增主键
     * @return mixed
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function insert(Query $query, bool $getLastInsID = false)
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if (empty($options['data'])) {
            throw new Exception('miss data to insert');
        }

        // 生成bulk对象
        $bulk         = $this->builder->insert($query);
        $writeConcern = $options['writeConcern'] ?? null;
        $writeResult  = $this->execute($options['table'], $bulk, $writeConcern);
        $result       = $writeResult->getInsertedCount();

        if ($result) {
            $data      = $options['data'];
            $lastInsId = $this->getLastInsID();

            if ($lastInsId) {
                $pk        = $query->getPk();
                $data[$pk] = $lastInsId;
            }

            $query->setOption('data', $data);

            $this->db->trigger('after_insert', $query);

            if ($getLastInsID) {
                return $lastInsId;
            }
        }

        return $result;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @return mixed
     */
    public function getLastInsID(string $sequence = null)
    {
        $id = $this->builder->getLastInsID();

        if (is_array($id)) {
            array_walk($id, function (&$item, $key) {
                if ($item instanceof ObjectID) {
                    $item = $item->__toString();
                }
            });
        } elseif ($id instanceof ObjectID) {
            $id = $id->__toString();
        }

        return $id;
    }

    /**
     * 批量插入记录
     * @access public
     * @param  Query $query 查询对象
     * @param  array $dataSet 数据集
     * @return integer
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function insertAll(Query $query, array $dataSet = []): int
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if (!is_array(reset($dataSet))) {
            return 0;
        }

        // 生成bulkWrite对象
        $bulk         = $this->builder->insertAll($query, $dataSet);
        $writeConcern = $options['writeConcern'] ?? null;
        $writeResult  = $this->execute($options['table'], $bulk, $writeConcern);

        return $writeResult->getInsertedCount();
    }

    /**
     * 更新记录
     * @access public
     * @param  Query     $query 查询对象
     * @return int
     * @throws Exception
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function update(Query $query): int
    {
        $options = $query->parseOptions();

        if (isset($options['cache'])) {
            $cacheItem = $this->parseCache($query, $options['cache']);
            $key       = $cacheItem->getKey();
        }

        // 生成bulkWrite对象
        $bulk         = $this->builder->update($query);
        $writeConcern = $options['writeConcern'] ?? null;
        $writeResult  = $this->execute($options['table'], $bulk, $writeConcern);

        // 检测缓存
        if (isset($key) && $this->cache->get($key)) {
            // 删除缓存
            $this->cache->delete($key);
        } elseif (isset($cacheItem) && $cacheItem->getTag()) {
            $this->cache->tag($cacheItem->getTag())->clear();
        }

        $result = $writeResult->getModifiedCount();

        if ($result) {
            $this->db->trigger('after_update', $query);
        }

        return $result;
    }

    /**
     * 删除记录
     * @access public
     * @param  Query     $query 查询对象
     * @return int
     * @throws Exception
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     * @throws BulkWriteException
     */
    public function delete(Query $query): int
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if (isset($options['cache'])) {
            $cacheItem = $this->parseCache($query, $options['cache']);
            $key       = $cacheItem->getKey();
        }

        // 生成bulkWrite对象
        $bulk = $this->builder->delete($query);

        $writeConcern = $options['writeConcern'] ?? null;

        // 执行操作
        $writeResult = $this->execute($options['table'], $bulk, $writeConcern);

        // 检测缓存
        if (isset($key) && $this->cache->get($key)) {
            // 删除缓存
            $this->cache->delete($key);
        } elseif (isset($cacheItem) && $cacheItem->getTag()) {
            $this->cache->tag($cacheItem->getTag())->clear();
        }

        $result = $writeResult->getDeletedCount();

        if ($result) {
            $this->db->trigger('after_delete', $query);
        }

        return $result;
    }

    /**
     * 查找记录
     * @access public
     * @param  Query $query 查询对象
     * @return Collection|array
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function select(Query $query)
    {
        $options = $query->parseOptions();

        if (!empty($options['cache'])) {
            $cacheItem = $this->parseCache($query, $options['cache']);
            $resultSet = $this->getCacheData($cacheItem);

            if (false !== $resultSet) {
                return $resultSet;
            }
        }

        // 生成MongoQuery对象
        $mongoQuery = $this->builder->select($query);

        $resultSet = $this->db->trigger('before_select', $query);

        if (!$resultSet) {
            // 执行查询操作
            $readPreference = $options['readPreference'] ?? null;

            $resultSet = $this->query($options['table'], $mongoQuery, $readPreference, $options['typeMap']);
        }

        if (isset($cacheItem) && false !== $resultSet) {
            // 缓存数据集
            $cacheItem->set($resultSet);
            $this->cacheData($cacheItem);
        }

        return $resultSet;
    }

    /**
     * 查找单条记录
     * @access public
     * @param  Query $query 查询对象
     * @return array
     * @throws ModelNotFoundException
     * @throws DataNotFoundException
     * @throws AuthenticationException
     * @throws InvalidArgumentException
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function find(Query $query)
    {
        // 分析查询表达式
        $options = $query->parseOptions();

        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cacheItem = $this->parseCache($query, $options['cache']);
            $key       = $cacheItem->getKey();
        }

        if (isset($key)) {
            $result = $this->cache->get($key);

            if (false !== $result) {
                return $result;
            }
        }

        // 生成查询对象
        $mongoQuery = $this->builder->select($query, true);

        // 事件回调
        $result = $this->db->trigger('before_find', $query);

        if (!$result) {
            // 执行查询
            $readPreference = $options['readPreference'] ?? null;
            $resultSet      = $this->query($options['table'], $mongoQuery, $readPreference, $options['typeMap']);

            $result = $resultSet[0] ?? null;
        }

        if (isset($cache) && $result) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return $result;
    }

    /**
     * 获取缓存数据
     * @access protected
     * @param  Query  $query 查询对象
     * @param  mixed  $cache 缓存设置
     * @param  array  $data  缓存数据
     * @param  string $key   缓存Key
     * @return mixed
     */
    protected function getCacheData(CacheItem $cacheItem)
    {
        // 判断查询缓存
        return $this->cache->get($cacheItem->getKey());
    }

    /**
     * 缓存数据
     * @access protected
     * @param  CacheItem $cacheItem 缓存Item
     */
    protected function cacheData(CacheItem $cacheItem): void
    {
        if ($cacheItem->getTag()) {
            $this->cache->tag($cacheItem->getTag());
        }

        $this->cache->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());
    }

    protected function parseCache(Query $query, array $cache): CacheItem
    {
        list($key, $expire, $tag) = $cache;

        if ($key instanceof CacheItem) {
            $cacheItem = $key;
        } else {
            if (true === $key) {
                if (!empty($query->getOptions('key'))) {
                    $key = 'think:' . $this->getConfig('database') . '.' . $query->getTable() . '|' . $query->getOptions('key');
                } else {
                    $key = md5($this->getConfig('database') . serialize($query->getOptions()));
                }
            }

            $cacheItem = new CacheItem($key);
            $cacheItem->expire($expire);
            $cacheItem->tag($tag);
        }

        return $cacheItem;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param  string $field 字段名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function value(Query $query, string $field, $default = null)
    {
        $options = $query->parseOptions();

        if (isset($options['projection'])) {
            $query->removeOption('projection');
        }

        $query->setOption('projection', (array) $field);

        if (!empty($options['cache'])) {
            $cacheItem = $this->parseCache($query, $options['cache']);
            $result    = $this->getCacheData($cacheItem);

            if (false !== $result) {
                return $result;
            }
        }

        $mongoQuery = $this->builder->select($query, true);

        if (isset($options['projection'])) {
            $query->setOption('projection', $options['projection']);
        } else {
            $query->removeOption('projection');
        }

        // 执行查询操作
        $readPreference = $options['readPreference'] ?? null;
        $resultSet      = $this->query($options['table'], $mongoQuery, $readPreference);

        if (!empty($resultSet)) {
            $data = array_shift($resultSet);

            $result = $data[$field];
        } else {
            $result = false;
        }

        if (isset($cacheItem) && false !== $result) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return false !== $result ? $result : $default;
    }

    /**
     * 得到某个列的数组
     * @access public
     * @param  string $field 字段名 多个字段用逗号分隔
     * @param  string $key 索引
     * @return array
     */
    public function column(Query $query, $field, string $key = '')
    {
        $options = $query->parseOptions();

        if (isset($options['projection'])) {
            $query->removeOption('projection');
        }

        if ($key && '*' != $field) {
            $projection = $key . ',' . $field;
        } else {
            $projection = $field;
        }

        $query->setOption('projection', $projection);

        if (!empty($options['cache'])) {
            // 判断查询缓存
            $cacheItem = $this->parseCache($query, $options['cache']);
            $result    = $this->getCacheData($cacheItem);

            if (false !== $result) {
                return $result;
            }
        }

        $mongoQuery = $this->builder->select($query);

        if (isset($options['projection'])) {
            $query->setOption('projection', $options['projection']);
        } else {
            $query->removeOption('projection');
        }

        // 执行查询操作
        $readPreference = $options['readPreference'] ?? null;
        $resultSet      = $this->query($options['table'], $mongoQuery, $readPreference);

        if (('*' == $field || strpos($field, ',')) && $key) {
            $result = array_column($resultSet, null, $key);
        } elseif (!empty($resultSet)) {
            $result = array_column($resultSet, $field, $key);
        } else {
            $result = [];
        }

        if (isset($cacheItem)) {
            // 缓存数据
            $cacheItem->set($result);
            $this->cacheData($cacheItem);
        }

        return $result;
    }

    /**
     * 延时更新检查 返回false表示需要延时
     * 否则返回实际写入的数值
     * @access public
     * @param string  $type     自增或者自减
     * @param string  $guid     写入标识
     * @param float   $step     写入步进值
     * @param integer $lazyTime 延时时间(s)
     * @return false|integer
     */
    public function lazyWrite(string $type, string $guid, float $step, int $lazyTime)
    {
        if (!$this->cache->has($guid . '_time')) {
            // 计时开始
            $this->cache->set($guid . '_time', time(), 0);
            $this->cache->$type($guid, $step);
        } elseif (time() > $this->cache->get($guid . '_time') + $lazyTime) {
            // 删除缓存
            $value = $this->cache->$type($guid, $step);
            $this->cache->delete($guid);
            $this->cache->delete($guid . '_time');
            return 0 === $value ? false : $value;
        } else {
            // 更新缓存
            $this->cache->$type($guid, $step);
        }

        return false;
    }

    /**
     * 执行command
     * @access public
     * @param  Query               $query      查询对象
     * @param  string|array|object $command 指令
     * @param  mixed               $extra 额外参数
     * @param  string              $db 数据库名
     * @return array
     */
    public function cmd(Query $query, $command, $extra = null, string $db = ''): array
    {
        if (is_array($command) || is_object($command)) {
            if ($this->getConfig('debug')) {
                $this->log('cmd', 'cmd', $command);
            }

            // 直接创建Command对象
            $command = new Command($command);
        } else {
            // 调用Builder封装的Command对象
            $command = $this->builder->$command($query, $extra);
        }

        return $this->command($command, $db);
    }

    // 获取当前数据表字段信息
    public function getTableFields(string $tableName)
    {
        return [];
    }

    // 获取当前数据表字段类型
    public function getFieldsType(string $tableName)
    {
        return [];
    }

    /**
     * 获取数据表绑定信息
     * @access public
     * @param mixed $tableName 数据表名
     * @return array
     */
    public function getFieldsBind($tableName): array
    {
        return [];
    }

    /**
     * 获取字段绑定类型
     * @access public
     * @param string $type 字段类型
     * @return integer
     */
    public function getFieldBindType(string $type): int
    {
        return 1;
    }

    /**
     * 启动事务
     * @access public
     * @return void
     * @throws \PDOException
     * @throws \Exception
     */
    public function startTrans()
    {}

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return void
     * @throws PDOException
     */
    public function commit()
    {}

    /**
     * 事务回滚
     * @access public
     * @return void
     * @throws PDOException
     */
    public function rollback()
    {}

    /**
     * 析构方法
     * @access public
     */
    public function __destruct()
    {
        // 释放查询
        $this->free();

        // 关闭连接
        $this->close();
    }
}
