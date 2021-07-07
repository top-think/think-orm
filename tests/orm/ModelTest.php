<?php

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\TestModel;
use think\Collection;
use think\facade\Db;
use function array_intersect_key;
use function count;

class ModelTest extends TestCase
{
    protected static $testUserData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_user`;');
        Db::execute(<<<SQL
CREATE TABLE `test_user` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `type` tinyint(4) NOT NULL DEFAULT '0',
     `username` varchar(32) NOT NULL,
     `nickname` varchar(32) NOT NULL,
     `password` varchar(32) NOT NULL,
     `create_time` int(10) UNSIGNED NOT NULL,
     `update_time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user`;');
    }

    public function testModel()
    {
        $data = [
            ['type' => 1, 'username' => 'qweqwe', 'nickname' => 'asdasd', 'password' => '123123'],
            ['type' => 2, 'username' => 'rtyrty', 'nickname' => 'fghfgh', 'password' => '456456'],
            ['type' => 3, 'username' => 'uiouio', 'nickname' => 'jkljkl', 'password' => '789789'],
        ];

        foreach ($data as $datum) {
            TestModel::create($datum);
        }

        /** @var TestModel[]|Collection $list */
        $list = TestModel::select();

        $this->assertEquals(count($data), TestModel::count());

        foreach ($list as $i => $item) {
            $this->assertTrue($item->id > 0);
            $this->assertTrue($item->create_time > 0);
            $this->assertTrue($item->update_time > 0);
            $this->assertEquals($data[$i], array_intersect_key($item->toArray(), $data[$i]));
        }
    }
}
