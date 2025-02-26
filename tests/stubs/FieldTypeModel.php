<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

/**
 * @property int $id
 * @property TestFieldJsonDTO $t_json
 * @property TestFieldPhpDTO $t_php
 * @property string $bigint
 */
class FieldTypeModel extends Model
{
    protected $table = 'test_field_type';
    protected $autoWriteTimestamp = false;

    protected $type = [
        't_json' => TestFieldJsonDTO::class,
        't_php' => TestFieldPhpDTO::class,
        'bigint' => 'string',
        'int_field' => 'integer',
        'float_field' => 'float',
        'bool_field' => 'boolean',
        'string_field' => 'string',
        'array_field' => 'array',
        'object_field' => 'object',
        'date_field' => 'date',
        'datetime_field' => 'datetime',
        'timestamp_field' => 'timestamp',
        'status' => UserStatus::class,
    ];

    // 定义获取器
    public function getFullNameAttr()
    {
        return 'test_' . $this->string_field;
    }
}
