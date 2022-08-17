<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/functions.php';

use think\facade\Db;
use think\Model;
// 数据库配置信息设置（全局有效）
Db::setConfig([
    // 默认数据连接标识
    'default'     => 'mysql',
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            // 数据库类型
            'type'     => 'mysql',
            // 主机地址
            'hostname' => '127.0.0.1',
            // 数据库名
            'database' => 'douhuomall',
            // 用户名
            'username' => 'root',
            // 密码
            'password' => 'cctv8u8',
            // 数据库编码默认采用utf8
            'charset'  => 'utf8',
            // 数据库表前缀
            'prefix'   => 'ys_',
            // 是否需要断线重连
            'break_reconnect' => false,
            // 断线标识字符串
            'break_match_str' => [],
            // 数据库调试模式
            'debug'    => false,
        ],
        'mysql_manage' => [
            // 数据库类型
            'type'     => 'mysql',
            // 主机地址
            'hostname' => '127.0.0.1',
            // 数据库名
            'database' => 'douhuomall_1',
            // 用户名
            'username' => 'root',
            // 密码
            'password' => 'cctv8u8',
            // 数据库编码默认采用utf8
            'charset'  => 'utf8',
            // 数据库表前缀
            'prefix'   => 'ys_',
            // 数据库调试模式
            'debug'    => false,
        ],
    ],
]);

class Order extends Model
{
    // 设置当前模型的数据库连接
    protected $connection = 'mysql_manage';
}

//$o = Order::where("id",1)->find();
$o = Order::where(1)->select();
print_r($o->toArray());
//$a = DB::name("order")->where("id",1)->find();
//$a->inc("num");