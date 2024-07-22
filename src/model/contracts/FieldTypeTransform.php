<?php

declare(strict_types=1);

namespace think\model\contracts;

use think\Model;

interface FieldTypeTransform
{
    /**
     * @param Model $model
     */
    public static function modelReadValue($value, $model);

    /**
     * @param Model $model
     */
    public static function modelWriteValue($value, $model);
}
