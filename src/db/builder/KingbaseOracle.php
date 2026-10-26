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
use think\db\Connection;

/**
 * KingbaseES Oracle 兼容模式 SQL 构造器。
 */
class KingbaseOracle extends Oracle
{
    /**
     * 使用 VALUES 语法构造多行 INSERT。
     *
     * PDO_KDB 无法在父 Oracle Builder 的 UNION ALL SELECT 批量写法中稳定推断
     * BLOB 等目标列的占位符类型，因此仅改写 SQL 结构；字段和值仍按原框架的
     * 数组迭代顺序处理。
     */
    public function insertAll(Query $query, array $dataSet): string
    {
        $options = $query->getOptions();
        $bind = $query->getFieldsBindType();

        if (empty($options['field']) || '*' == $options['field']) {
            $allowFields = array_keys($bind);
        } else {
            $allowFields = $options['field'];
        }

        $values = [];
        $insertFields = null;

        foreach ($dataSet as $data) {
            $data = $this->parseData($query, $data, $allowFields, $bind);

            if (null === $insertFields) {
                $insertFields = array_keys($data);
            }
            $values[] = '( ' . implode(',', array_values($data)) . ' )';
        }

        if (null === $insertFields) {
            return '';
        }

        $fields = [];
        foreach ($insertFields as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        return str_replace(
            ['%INSERT%', '%TABLE%', '%EXTRA%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $fields),
                'VALUES ' . implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /** 使用 VALUES 语法构造固定字段批量 INSERT。 */
    public function insertAllByKeys(Query $query, array $keys, array $datas): string
    {
        $options = $query->getOptions();
        $bind = $query->getFieldsBindType();
        $fields = [];
        $values = [];

        foreach ($keys as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        foreach ($datas as $data) {
            foreach ($data as $key => &$val) {
                $field = $keys[$key];

                if (!$query->isAutoBind()) {
                    $val = Connection::PARAM_STR == $bind[$field] ? '\'' . $val . '\'' : $val;
                } else {
                    $val = $this->parseDataBind($query, $field, $val, $bind);
                }
            }

            $values[] = '( ' . implode(',', $data) . ' )';
        }

        return str_replace(
            ['%INSERT%', '%TABLE%', '%EXTRA%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $fields),
                'VALUES ' . implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /**
     * 生成 Oracle 兼容模式随机排序表达式。
     *
     * KingbaseES Oracle 兼容模式未暴露 DBMS_RANDOM 包，因此使用 random()。
     *
     * @param Query $query 当前查询对象，保留父方法接口一致性
     *
     * @return string random() 表达式
     */
    protected function parseRand(Query $query): string
    {
        return 'random()';
    }

}
