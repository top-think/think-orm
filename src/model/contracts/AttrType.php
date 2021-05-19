<?php
declare(strict_types=1);

namespace think\model\contracts;

use think\Model;

interface AttrType
{
    /**
     * @param Model  $model
     * @param string $name
     * @param mixed  $value
     * @param array  $data
     * @return mixed
     */
    public function readValue(Model $model, string $name, $value, array $data);

    /**
     * @param Model  $model
     * @param string $name
     * @param mixed  $value
     * @param array  $data
     * @return mixed
     */
    public function writeValue(Model $model, string $name, $value, array $data);
}
