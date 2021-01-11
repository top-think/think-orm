<?php
declare(strict_types=1);

namespace think\model\contracts;

use think\Model;

interface FieldTypeTransform
{
    /**
     * @param mixed $value
     * @param Model $model
     * @return mixed
     */
    public static function modelReadValue($value, $model);

    /**
     * @param mixed $value
     * @param Model $model
     * @return mixed
     */
    public static function modelWriteValue($value, $model);
}
