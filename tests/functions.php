<?php

namespace tests;

use function array_column;
use function array_combine;
use function array_map;
use function sort;

function array_column_ex(array $arr, array $column, ?string $key = null): array
{
    $result = array_map(function ($val) use ($column) {
        $item = [];
        foreach ($column as $key) {
            $item[$key] = $val[$key];
        }
        return $item;
    }, $arr);

    if (!empty($key)) {
        $result = array_combine(array_column($arr, 'id'), $result);
    }

    return $result;
}

function array_value_sort(array $arr)
{
    foreach ($arr as &$value) {
        sort($value);
    }
}