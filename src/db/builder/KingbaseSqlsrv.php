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
 * KingbaseES SQL Server 兼容模式 SQL 构造器。
 */
class KingbaseSqlsrv extends Sqlsrv
{
    /**
     * 使用 VALUES 语法构造多行 INSERT。
     *
     * 避免父 SQL Server 方言的 UNION SELECT 批量写法，使 KingbaseES 能依据目标列
     * 正确推断每个占位符的数据类型。
     *
     * @param Query $query   当前查询对象
     * @param array $dataSet 多行待插入数据
     *
     * 仅改写父 SQL Server Builder 的 SQL 结构；各行字段和值仍按照原框架的
     * 数组迭代顺序处理，不在适配层重新排序或增加字段集合校验。
     *
     * @return string 完整批量 INSERT SQL
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
            ['%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                'VALUES ' . implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /**
     * 按固定字段集合使用 VALUES 语法构造多行 INSERT。
     *
     * 根据查询是否启用自动绑定生成字面量或参数占位符，并确保所有行复用同一字段顺序。
     *
     * @param Query $query 当前查询对象
     * @param array $keys  固定字段名列表
     * @param array $datas 按字段顺序组织的多行数据
     *
     * @return string 完整批量 INSERT SQL
     */
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
            ['%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                'VALUES ' . implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

}
