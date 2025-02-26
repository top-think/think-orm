<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class JsonModel extends Model
{
    protected $table = 'test_json_model';
    protected $autoWriteTimestamp = true;
    protected $jsonAssoc = true;
}