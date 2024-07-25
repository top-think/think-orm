<?php

declare(strict_types=1);

namespace think\model\contracts;

use think\Model;

interface FieldTypeTransform
{
    public static function modelReadValue(mixed $value, Model $model): static;

    /**
     * @return static|mixed
     */
    public static function modelWriteValue($value, Model $model): mixed;
}
