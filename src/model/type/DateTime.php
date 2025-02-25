<?php

declare (strict_types = 1);

namespace think\model\type;

use think\model\contract\Modelable;
use think\model\contract\Typeable;

class DateTime implements Typeable
{
    protected $data;
    protected $value;

    public static function from(mixed $value, Modelable $model)
    {
        $static = new static();
        $static->data($value, $model->getDateFormat());
        return $static;
    }

    public function data($time, $format)
    {
        $date = new \DateTime;

        $this->value = is_numeric($time) ? (int) $time : strtotime($time);
        $this->data  = $date->setTimestamp($this->value)->format($format);
    }

    public function format($format)
    {
        $date = new \DateTime;
        return $date->setTimestamp($this->value)->format($format);
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
        return $this->data;
    }
}
