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

namespace think;

use Exception;
use InvalidArgumentException;
use think\Container;
use think\db\Connection;
use think\db\Query;
use think\db\Raw;
use think\exception\DbException;

/**
 * Class Db
 * @package think
 * @mixin Query
 */
class Db
{
    /**
     * 当前数据库连接对象
     * @var Connection
     */
    protected $connection;

    /**
     * 数据库连接实例
     * @var array
     */
    protected $instance = [];

    /**
     * 数据库配置
     * @var array
     */
    protected $config = [];

    /**
     * Event
     * @var array
     */
    protected $event = [];

    /**
     * SQL监听
     * @var array
     */
    protected $listen = [];

    /**
     * 查询次数
     * @var int
     */
    protected $queryTimes = 0;

    /**
     * 架构函数
     * @param array $config 连接配置
     * @access public
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 切换数据库连接
     * @access public
     * @param mixed       $config 连接配置
     * @param bool|string $name   连接标识 true 强制重新连接
     * @return $this
     */
    public function connect($config = [], $name = false)
    {
        $this->connection = $this->instance($this->parseConfig($config), $name);
        return $this;
    }

    /**
     * 取得数据库连接类实例
     * @access public
     * @param array       $config 连接配置
     * @param bool|string $name   连接标识 true 强制重新连接
     * @return Connection
     */
    public function instance(array $config = [], $name = false)
    {
        if (false === $name) {
            $name = md5(serialize($config));
        }

        if (true === $name || !isset($this->instance[$name])) {

            if (empty($config['type'])) {
                throw new InvalidArgumentException('Undefined db type');
            }

            if (true === $name) {
                $name = md5(serialize($config));
            }

            $this->instance[$name] = $this->factory($config['type'], '\\think\\db\\connector\\', $config);
        }

        return $this->instance[$name];
    }

    /**
     * 创建工厂对象实例
     * @access public
     * @param string $name      工厂类名
     * @param string $namespace 默认命名空间
     * @param array  $args
     * @return mixed
     */
    public function factory(string $name, string $namespace = '', ...$args)
    {
        $class = false !== strpos($name, '\\') ? $name : $namespace . ucwords($name);

        if (class_exists($class)) {
            return Container::getInstance()->invokeClass($class, $args);
        }

        throw new Exception('class not exists:' . $class);
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param string $value 表达式
     * @return Raw
     */
    public function raw(string $value): Raw
    {
        return new Raw($value);
    }

    /**
     * 更新查询次数
     * @access public
     * @return void
     */
    public function updateQueryTimes(): void
    {
        $this->queryTimes++;
    }

    /**
     * 重置查询次数
     * @access public
     * @return void
     */
    public function clearQueryTimes(): void
    {
        $this->queryTimes = 0;
    }

    /**
     * 获得查询次数
     * @access public
     * @return integer
     */
    public function getQueryTimes(): int
    {
        return $this->queryTimes;
    }

    /**
     * 数据库连接参数解析
     * @access private
     * @param mixed $config
     * @return array
     */
    private function parseConfig($config): array
    {
        if (empty($config)) {
            $config = $this->config;
        } elseif (is_string($config) && isset($this->config[$config])) {
            // 支持读取配置参数
            $config = $this->config[$config];
        }

        if (!is_array($config)) {
            throw new DbException('database config error:' . $config);
        }

        return $config;
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param string $name 参数名称
     * @return mixed
     */
    public function getConfig(string $name = '')
    {
        return $name ? ($this->config[$name] ?? null) : $this->config;
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @param string|array $connection 连接配置信息
     * @return mixed
     */
    public function buildQuery($connection = [])
    {
        $connection = $this->instance($this->parseConfig($connection));
        return $this->newQuery($connection);
    }

    /**
     * 监听SQL执行
     * @access public
     * @param callable $callback 回调方法
     * @return void
     */
    public function listen(callable $callback): void
    {
        $this->listen[] = $callback;
    }

    /**
     * 获取监听SQL执行
     * @access public
     * @return array
     */
    public function getListen(): array
    {
        return $this->listen;
    }

    /**
     * 注册回调方法
     * @access public
     * @param string   $event    事件名
     * @param callable $callback 回调方法
     * @return void
     */
    public function event(string $event, callable $callback): void
    {
        $this->event[$event] = $callback;
    }

    /**
     * 触发事件
     * @access public
     * @param string $event  事件名
     * @param mixed  $params 传入参数
     * @param bool   $once
     * @return mixed
     */
    public function trigger(string $event, $params = null, bool $once = false)
    {
        if (isset($this->event[$event])) {
            return call_user_func_array($this->event[$event], [$this]);
        }
    }

    /**
     * 创建一个新的查询对象
     * @access protected
     * @param Connection $connection 连接对象
     * @return mixed
     */
    protected function newQuery($connection = null)
    {
        /** @var Query $query */
        if (is_null($connection) && !$this->connection) {
            $this->connect($this->config);
        }

        $connection = $connection ?: $this->connection;
        $class      = $connection->getQueryClass();
        $query      = new $class($connection);

        $query->setDb($this);

        return $query;
    }

    /**
     * 字符串命名风格转换
     * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
     * @access public
     * @param  string  $name 字符串
     * @param  integer $type 转换类型
     * @param  bool    $ucfirst 首字母是否大写（驼峰规则）
     * @return string
     */
    public function parseName(string $name = null, int $type = 0, bool $ucfirst = true): string
    {
        if ($type) {
            $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
                return strtoupper($match[1]);
            }, $name);
            return $ucfirst ? ucfirst($name) : lcfirst($name);
        }

        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }

    /**
     * 获取类名(不包含命名空间)
     * @access public
     * @param string|object $class
     * @return string
     */
    public function classBaseName($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    public function __call($method, $args)
    {
        $query = $this->newQuery($this->connection);

        return call_user_func_array([$query, $method], $args);
    }
}
