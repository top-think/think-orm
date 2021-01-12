<?php
declare(strict_types=1);

namespace think\model\contracts;

use think\Model;

interface FieldTypeTransform
{
    /**
     * @param string $name
     * @param mixed  $value
     * @param Model  $model
     * @return mixed
     */
    public static function modelReadValue($name, $value, $model);

    /**
     * @param string $name
     * @param mixed  $value
     * @param Model  $model
     * @return mixed
     */
    public static function modelWriteValue($name, $value, $model);
}
