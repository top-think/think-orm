<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\model\type;

/**
 * KingbaseES日期时间类型。
 *
 * SQL Server兼容模式的PDO_KDB会把DATETIME2小数秒返回为7位，而PHP的`u`
 * 格式只接受最多6位微秒。进入ThinkORM原有日期转换前截断多余精度，避免分页器、
 * 模型数组转换和JSON序列化时得到无效DateTime对象。
 */
final class KingbaseDateTime extends DateTime
{
    /**
     * 规范化KingbaseES日期时间值后复用ThinkORM原有转换逻辑。
     *
     * @param mixed  $time   数据库返回值
     * @param mixed  $format 模型日期输出格式
     *
     * @return void
     */
    public function data($time, $format)
    {
        if (is_string($time) && preg_match('/^(.*\.)(\d{7,})(.*)$/', $time, $matches)) {
            $time = $matches[1] . substr($matches[2], 0, 6) . $matches[3];
        }

        parent::data($time, $format);
    }
}
