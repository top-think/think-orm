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

use Psr\SimpleCache\CacheInterface;
use think\db\Connection;

class Db
{
    /**
     * 数据库配置
     * @var array
     */
    protected static $config = [];

    /**
     * 查询类自动映射
     * @var array
     */
    protected static $queryMap = [
        'mongo' => '\\think\\db\\Mongo',
    ];

    /**
     * 缓存对象
     * @var object
     */
    protected static $cacheHandler;

    public static function setConfig($config)
    {
        self::$config = $config;
    }

    public static function getConfig($name = null)
    {
        return $name ? (self::$config[$name] ?? null) : self::$config;
    }

    public static function setCacheHandler(CacheInterface $cacheHandler)
    {
        self::$cacheHandler = $cacheHandler;
    }

    public static function getCacheHandler()
    {
        return self::$cacheHandler;
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @param  string $query        查询对象类名
     * @param  mixed  $connection   连接配置信息
     * @return mixed
     */
    public static function buildQuery($query, $connection = [])
    {
        $connection = Connection::instance(self::parseConfig($connection));
        return new $query($connection);
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
    public static function parseName(string $name = null, int $type = 0, bool $ucfirst = true): string
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
     * @param  string|object $class
     * @return string
     */
    public static function classBaseName($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    /**
     * 数据库连接参数解析
     * @access private
     * @param  mixed $config
     * @return array
     */
    private static function parseConfig($config): array
    {
        if (empty($config)) {
            $config = self::$config;
        } elseif (is_string($config) && false === strpos($config, '/')) {
            // 支持读取配置参数
            $config = self::$config[$config] ?? static::$config;
        }

        return is_string($config) ? self::parseDsnConfig($config) : $config;
    }

    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @access private
     * @param  string $dsnStr
     * @return array
     */
    private static function parseDsnConfig(string $dsnStr): array
    {
        $info = parse_url($dsnStr);

        if (!$info) {
            return [];
        }

        $dsn = [
            'type'     => $info['scheme'],
            'username' => $info['user'] ?? '',
            'password' => $info['pass'] ?? '',
            'hostname' => $info['host'] ?? '',
            'hostport' => $info['port'] ?? '',
            'database' => !empty($info['path']) ? ltrim($info['path'], '/') : '',
            'charset'  => $info['fragment'] ?? 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }

        return $dsn;
    }

    public static function __callStatic($method, $args)
    {
        $type  = strtolower(self::getConfig('type'));
        $class = isset(self::$queryMap[$type]) ? self::$queryMap[$type] : '\\think\\db\\Query';

        $query = static::buildQuery($class, self::$config);

        return call_user_func_array([$query, $method], $args);
    }
}
