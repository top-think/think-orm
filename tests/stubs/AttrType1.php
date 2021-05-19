<?php

namespace tests\stubs;

use think\Model;
use think\model\contracts\AttrType;

class AttrType1 implements AttrType
{
    protected $inc;
    protected $mul;

    public static function init1(): AttrType1
    {
        return new self(0, 1);
    }

    public static function init2($args): AttrType1
    {
        return new self($args[0] ?? 0, $args[1] ?? 1);
    }

    public function __construct($inc = 0, $mul = 1)
    {
        $this->inc = $inc;
        $this->mul = $mul;
    }

    public function readValue(Model $model, string $name, $value, array $data)
    {
        return ($value + $this->inc) * $this->mul;
    }

    public function writeValue(Model $model, string $name, $value, array $data)
    {
        return $value / $this->mul - $this->inc;
    }
}
