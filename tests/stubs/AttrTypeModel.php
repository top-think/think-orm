<?php

namespace tests\stubs;

use think\Model;

class AttrTypeModel extends Model
{
    protected $table = 'test_attr_type';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $dateFormat = false;

    protected $type = [
        'num1' => AttrType1::class,
        'num2' => '\think\Model\AttrType1:1,2',
        'num3' => AttrType1::class . '::init1',
        'num4' => '\think\Model\AttrType1::init2:1,2',
    ];
}