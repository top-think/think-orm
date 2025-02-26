<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class AccessorModel extends Model
{
    protected $table = 'test_accessor_model';
    protected $append = ['price_cent'];

    // 定义name字段获取器
    public function getNameAttr($value)
    {
        return strtoupper($value);
    }

    // 定义status字段获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']];
    }

    // 定义name字段修改器
    public function setNameAttr($value)
    {
        return ucfirst($value);
    }

    // 定义price字段修改器
    public function setPriceAttr($value)
    {
        return number_format($value, 2, '.', '');
    }

    // 定义extra字段的组合获取器和修改器
    public function getExtraAttr($value)
    {
        return json_decode($value, true);
    }

    public function setExtraAttr($value)
    {
        return json_encode($value);
    }

    // 定义price字段获取器，在序列化时转换为整数分
    public function getPriceCentAttr($value, $data)
    {
        return intval($data['price'] * 100);
    }

    // 定义name字段搜索器
    public function searchNameAttr($query, $value)
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    // 定义status字段搜索器
    public function searchStatusAttr($query, $value)
    {
        $query->where('status', '=', $value);
    }

    // 定义带参数的price搜索器
    public function searchPriceAttr($query, $value, $data)
    {
        if (isset($data['min_price']) && isset($data['max_price'])) {
            $query->whereBetween('price', [$data['min_price'], $data['max_price']]);
        }
    }

    // 定义组合搜索器
    public function searchComplexAttr($query, $value, $data)
    {
        if (!empty($data['keyword'])) {
            $query->where('name', 'like', '%' . $data['keyword'] . '%');
        }

        if (isset($data['status'])) {
            $query->where('status', '=', $data['status']);
        }

        if (isset($data['min_price'])) {
            $query->where('price', '>=', $data['min_price']);
        }
    }
}