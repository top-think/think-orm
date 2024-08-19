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
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $dateFormat = false;

    protected $type = [
        't_json' => TestFieldJsonDTO::class,
        't_php' => TestFieldPhpDTO::class,
        'bigint' => 'string',
    ];
}
