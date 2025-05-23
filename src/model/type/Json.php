<?php

declare (strict_types = 1);

namespace think\model\type;

use think\model\contract\Modelable;
use think\model\contract\Typeable;

class Json implements Typeable
{
    protected $data;

    public static function from(mixed $value, Modelable $model)
    {
        $static = new static();
        $static->data($value, $model->isJsonAssoc());
        return $static;
    }

    public function data($data, ?bool $assoc)
    {
        if (is_string($data) && json_validate($data)) {
            $data = json_decode($data, $assoc);
        } elseif (empty($data)) {
            $data = [];
        }
        $this->data = is_string($data) ? [$data] : $data;
    }

    public function value()
    {
        return $this->data;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->data, JSON_UNESCAPED_UNICODE);
    }
}
