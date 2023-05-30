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
declare(strict_types=1);

namespace think\db\concern;

use think\db\exception\DbException;
use think\db\Raw;

/**
 * 聚合查询.
 */
trait AggregateQuery
{
    /**
     * 聚合查询.
     *
     * @param string     $aggregate 聚合方法
     * @param string|Raw $field     字段名
     * @param bool       $force     强制转为数字类型
     *
     * @return mixed
     */
    protected function aggregate(string $aggregate, string|Raw $field, bool $force = false, bool $one = false)
    {
        return $this->connection->aggregate($this, $aggregate, $field, $force, $one);
    }

    /**
     * COUNT查询.
     *
     * @param string $field 字段名
     *
     * @return int
     */
    public function count(string $field = '*'): int
    {
        if (!empty($this->options['group'])) {
            // 支持GROUP

            if (!preg_match('/^[\w\.\*]+$/', $field)) {
                throw new DbException('not support data:' . $field);
            }

            $options = $this->getOptions();
            if (isset($options['cache'])) {
                $cache = $options['cache'];
                unset($options['cache']);
            }

            $subSql = $this->options($options)
                ->field('count(' . $field . ') AS think_count')
                ->bind($this->bind)
                ->buildSql();

            $query = $this->newQuery();
            if (isset($cache)) {
                $query->setOption('cache', $cache);
            }
            $query->table([$subSql => '_group_count_']);

            $count = $query->aggregate('COUNT', '*');
        } else {
            $count = $this->aggregate('COUNT', $field);
        }

        return (int) $count;
    }

    /**
     * SUM查询.
     *
     * @param string|Raw $field 字段名
     *
     * @return float
     */
    public function sum(string|Raw $field): float
    {
        return $this->aggregate('SUM', $field, true);
    }

    /**
     * MIN查询.
     *
     * @param string|Raw $field 字段名
     * @param bool       $force 强制转为数字类型
     *
     * @return mixed
     */
    public function min(string|Raw $field, bool $force = true)
    {
        return $this->aggregate('MIN', $field, $force);
    }

    /**
     * MAX查询.
     *
     * @param string|Raw $field 字段名
     * @param bool       $force 强制转为数字类型
     *
     * @return mixed
     */
    public function max(string|Raw $field, bool $force = true)
    {
        return $this->aggregate('MAX', $field, $force);
    }

    /**
     * AVG查询.
     *
     * @param string|Raw $field 字段名
     *
     * @return float
     */
    public function avg(string|Raw $field): float
    {
        return $this->aggregate('AVG', $field, true);
    }
}
