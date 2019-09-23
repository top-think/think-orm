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

namespace think\db;

use Psr\SimpleCache\CacheInterface;
use think\DbManager;
use think\db\CacheItem;

/**
 * 数据库连接基础类
 */
abstract class Connection
{

    /**
     * 当前SQL指令
     * @var string
     */
    protected $queryStr = '';

    /**
     * 返回或者影响记录数
     * @var int
     */
    protected $numRows = 0;

    /**
     * 事务指令数
     * @var int
     */
    protected $transTimes = 0;

    /**
     * 错误信息
     * @var string
     */
    protected $error = '';

    /**
     * 数据库连接ID 支持多个连接
     * @var array
     */
    protected $links = [];

    /**
     * 当前连接ID
     * @var object
     */
    protected $linkID;

    /**
     * 当前读连接ID
     * @var object
     */
    protected $linkRead;

    /**
     * 当前写连接ID
     * @var object
     */
    protected $linkWrite;

    /**
     * 数据表信息
     * @var array
     */
    protected $info = [];

    /**
     * 查询开始时间
     * @var float
     */
    protected $queryStartTime;

    /**
     * Builder对象
     * @var Builder
     */
    protected $builder;

    /**
     * Db对象
     * @var Db
     */
    protected $db;

    /**
     * 是否读取主库
     * @var bool
     */
    protected $readMaster = false;

    /**
     * 数据库连接参数配置
     * @var array
     */
    protected $config = [];

    /**
     * 缓存对象
     * @var Cache
     */
    protected $cache;

    /**
     * 获取当前的builder实例对象
     * @access public
     * @return Builder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * 设置当前的数据库Db对象
     * @access public
     * @param DbManager $db
     * @return void
     */
    public function setDb(DbManager $db)
    {
        $this->db = $db;
    }

    /**
     * 设置当前的缓存对象
     * @access public
     * @param CacheInterface $cache
     * @return void
     */
    public function setCache(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * 获取当前的缓存对象
     * @access public
     * @return CacheInterface|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param string $config 配置名称
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
     * @param array $config 配置
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 数据库SQL监控
     * @access protected
     * @param string $sql    执行的SQL语句 留空自动获取
     * @param bool   $master  主从标记
     * @return void
     */
    protected function trigger(string $sql = '', bool $master = false): void
    {
        $listen = $this->db->getListen();

        if (!empty($listen)) {
            $runtime = number_format((microtime(true) - $this->queryStartTime), 6);
            $sql     = $sql ?: $this->getLastsql();

            if (empty($this->config['deploy'])) {
                $master = null;
            }

            foreach ($listen as $callback) {
                if (is_callable($callback)) {
                    $callback($sql, $runtime, $master);
                }
            }
        }
    }

    /**
     * 缓存数据
     * @access protected
     * @param CacheItem $cacheItem 缓存Item
     */
    protected function cacheData(CacheItem $cacheItem)
    {
        if ($cacheItem->getTag() && method_exists($this->cache, 'tag')) {
            $this->cache->tag($cacheItem->getTag())->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());
        } else {
            $this->cache->set($cacheItem->getKey(), $cacheItem->get(), $cacheItem->getExpire());
        }
    }

    /**
     * 分析缓存Key
     * @access protected
     * @param BaseQuery $query 查询对象
     * @return string
     */
    protected function getCacheKey(BaseQuery $query): string
    {
        if (!empty($query->getOptions('key'))) {
            $key = 'think:' . $this->getConfig('database') . '.' . $query->getTable() . '|' . $query->getOptions('key');
        } else {
            $key = $query->getQueryGuid();
        }

        return $key;
    }

    /**
     * 分析缓存
     * @access protected
     * @param BaseQuery $query 查询对象
     * @param array $cache 缓存信息
     * @return CacheItem
     */
    protected function parseCache(BaseQuery $query, array $cache): CacheItem
    {
        list($key, $expire, $tag) = $cache;

        if ($key instanceof CacheItem) {
            $cacheItem = $key;
        } else {
            if (true === $key) {
                $key = $this->getCacheKey($query);
            }

            $cacheItem = new CacheItem($key);
            $cacheItem->expire($expire);
            $cacheItem->tag($tag);
        }

        return $cacheItem;
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
        if (!$this->cache || !method_exists($this->cache, $type)) {
            return $step;
        }

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
     * 获取返回或者影响的记录数
     * @access public
     * @return integer
     */
    public function getNumRows(): int
    {
        return $this->numRows;
    }

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
