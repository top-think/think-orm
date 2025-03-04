<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class FieldTypeModel extends Model
{
    protected $table = 'test_field_type';
    protected $autoWriteTimestamp = false;

    protected $type = [
        'array_field'   => 'array',
        'object_field'  => 'object',
        'bool_field'    =>  'bool',
    ];

    public function __construct(array $data = [])
    {
        if (PHP_VERSION_ID >= 80100) {
            $this->type['status'] = UserStatus::class;
        }

        parent::__construct($data);
    }

    // 定义获取器
    public function getFullNameAttr()
    {
        return 'test_' . $this->string_field;
    }
}
