<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\db\builder;

use think\db\BaseQuery as Query;
use think\db\Raw;

/**
 * KingbaseES MySQL 兼容模式 SQL 构造器。
 */
class KingbaseMysql extends Mysql
{
    /**
     * 生成数据字段的参数占位符，并适配 JSON 路径更新。
     *
     * KingbaseES MySQL 兼容函数有时无法推断 JSON 路径更新值对应的 PDO 占位符
     * 类型，因此将该占位符显式转换为 TEXT；普通字段沿用父构造器行为。
     *
     * @param Query  $query 当前查询对象
     * @param string $key   字段名或 JSON 路径
     * @param mixed  $data  待绑定数据
     * @param array  $bind  字段绑定类型映射
     *
     * @return string SQL 参数占位表达式
     */
    protected function parseDataBind(Query $query, string $key, $data, array $bind = []): string
    {
        $placeholder = parent::parseDataBind($query, $key, $data, $bind);

        if (str_contains($key, '->')) {
            return 'CAST(' . $placeholder . ' AS TEXT)';
        }

        return $placeholder;
    }

    /**
     * 将兼容函数使用的字符串占位符显式转换为 TEXT。
     *
     * Raw 表达式保持原样解析，冒号开头的绑定占位符增加 CAST，其余值直接转为字符串。
     *
     * @param Query $query 当前查询对象
     * @param mixed $value 原始值、占位符或 Raw 表达式
     *
     * @return string 可拼入 SQL 的文本表达式
     */
    protected function parseTextValue(Query $query, $value): string
    {
        if ($value instanceof Raw) {
            return $this->parseRaw($query, $value);
        }

        if (is_string($value) && str_starts_with($value, ':')) {
            return 'CAST(' . $value . ' AS TEXT)';
        }

        return (string) $value;
    }

    /**
     * 构造 MySQL 兼容模式的 FIND_IN_SET 条件。
     *
     * @param Query  $query 当前查询对象
     * @param string $key   已解析的字段表达式
     * @param string $exp   查询运算符，保留父方法接口一致性
     * @param mixed  $value 待查找值或绑定占位符
     * @param string $field 原始字段名，保留父方法接口一致性
     *
     * @return string FIND_IN_SET 函数 SQL
     */
    protected function parseFindInSet(Query $query, string $key, string $exp, $value, string $field): string
    {
        return 'FIND_IN_SET(' . $this->parseTextValue($query, $value) . ', ' . $key . ')';
    }
}
