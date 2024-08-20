<?php

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/functions.php';

use think\facade\Db;

// 数据库配置信息设置（全局有效）
Db::setConfig([
    // 默认数据连接标识
    'default' => 'mysql',
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type' => 'mysql',
            // 主机地址
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            // 主机端口
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            // 数据库名
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            // 用户名
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            // 密码
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => 'test_',
            // 是否需要断线重连
            'break_reconnect' => false,
            // 断线标识字符串
            'break_match_str' => [],
            // 数据库调试模式
            'debug' => false,
        ],
        'mysql_manage' => [
            // 数据库类型
            'type' => 'mysql',
            // 主机地址
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            // 主机端口
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            // 数据库名
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            // 用户名
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            // 密码
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            // 数据库编码默认采用utf8
            'charset' => 'utf8',
            // 数据库表前缀
            'prefix' => 'test_',
            // 数据库调试模式
            'debug' => false,
        ],
    ],
]);
