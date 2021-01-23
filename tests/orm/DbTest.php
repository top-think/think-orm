<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use function array_column;
use function array_keys;
use function array_values;

class DbTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_user`;');
        Db::execute(<<<SQL
CREATE TABLE `test_user` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `type` tinyint(4) NOT NULL DEFAULT '0',
     `username` varchar(32) NOT NULL,
     `nickname` varchar(32) NOT NULL,
     `password` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function testColumn()
    {
        Db::execute('TRUNCATE TABLE `test_user`;');
        $users = [
            ['id' => '1', 'type' => '3', 'username' => 'qweqwe', 'nickname' => 'asdasd', 'password' => '123123'],
            ['id' => '2', 'type' => '2', 'username' => 'rtyrty', 'nickname' => 'fghfgh', 'password' => '456456'],
            ['id' => '3', 'type' => '1', 'username' => 'uiouio', 'nickname' => 'jkljkl', 'password' => '789789'],
        ];

        Db::table('test_user')->insertAll($users);
        $result = Db::table('test_user')->column('*', 'id');

        $this->assertCount(3, $result);
        $this->assertEquals($users, array_values($result));
        $this->assertEquals(array_column($users, 'id'), array_keys($result));

        $result = Db::table('test_user')->column('username');

        $this->assertEquals(array_column($users, 'username'), $result);
    }
}
