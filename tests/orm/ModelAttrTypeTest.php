<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\AttrTypeModel;
use think\facade\Db;

class ModelAttrTypeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_attr_type`;');
        Db::execute(<<<SQL
CREATE TABLE `test_attr_type` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `num1` tinyint(4) NOT NULL DEFAULT '0',
     `num2` tinyint(4) NOT NULL DEFAULT '0',
     `num3` tinyint(4) NOT NULL DEFAULT '0',
     `num4` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function testAttrType()
    {
        $data = [
            ['num1' => 1, 'num2' => (1 + 1) * 2, 'num3' => 1, 'num4' => (1 + 1) * 2],
            ['num1' => 2, 'num2' => (2 + 1) * 2, 'num3' => 2, 'num4' => (2 + 1) * 2],
            ['num1' => 3, 'num2' => (3 + 1) * 2, 'num3' => 3, 'num4' => (3 + 1) * 2],
        ];

        foreach ($data as $datum) {
            AttrTypeModel::create($datum);
        }

        foreach (AttrTypeModel::select()->toArray() as $index => $item) {
            unset($item['id']);
            $this->assertEquals($item, $data[$index]);
        }

    }
}
