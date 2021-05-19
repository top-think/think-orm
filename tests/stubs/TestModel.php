<?php

namespace tests\stubs;

use think\Model;

/**
 * Class ModelA
 * @package tests\model
 * @property int $id
 * @property int $type
 * @property string $username
 * @property string $nickname
 * @property int $create_time
 * @property int $update_time
 */
class TestModel extends Model
{
    protected $table = 'test_user';
    protected $pk = 'id';

    protected $autoWriteTimestamp = true;
    protected $dateFormat = false;
}
