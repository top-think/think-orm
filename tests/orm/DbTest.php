<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\Raw;
use think\facade\Db;
use function array_column;
use function array_keys;
use function array_unique;
use function array_values;
use function tests\array_column_ex;
use function tests\array_value_sort;

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
            ['id' => 1, 'type' => 3, 'username' => 'qweqwe', 'nickname' => 'asdasd', 'password' => '123123'],
            ['id' => 2, 'type' => 2, 'username' => 'rtyrty', 'nickname' => 'fghfgh', 'password' => '456456'],
            ['id' => 3, 'type' => 1, 'username' => 'uiouio', 'nickname' => 'jkljkl', 'password' => '789789'],
            ['id' => 5, 'type' => 2, 'username' => 'qazqaz', 'nickname' => 'wsxwsx', 'password' => '098098'],
            ['id' => 7, 'type' => 2, 'username' => 'rfvrfv', 'nickname' => 'tgbtgb', 'password' => '765765'],
        ];

        // 获取全部列
        Db::table('test_user')->insertAll($users);
        $result = Db::table('test_user')->column('*', 'id');

        $this->assertCount(5, $result);
        $this->assertEquals($users, array_values($result));
        $this->assertEquals(array_column($users, 'id'), array_keys($result));

        // 获取某一个字段
        $result = Db::table('test_user')->column('username');
        $this->assertEquals(array_column($users, 'username'), $result);

        // 获取某字段唯一
        $result = Db::table('test_user')->distinct(true)->column('type');
        $expected = array_unique(array_column($users, 'type'));
        $this->assertEquals($expected, $result);

        // 字段别名
        $result = Db::table('test_user')->column('username as name2');
        $expected = array_column($users, 'username');
        $this->assertEquals($expected, $result);

        // 表别名
        $result = Db::table('test_user')->alias('test2')->column('test2.username');
        $expected = array_column($users, 'username');
        $this->assertEquals($expected, $result);

        // 获取若干列
        $result = Db::table('test_user')->column('username,nickname', 'id');
        $expected = array_column_ex($users, ['username', 'nickname', 'id'], 'id');
        $this->assertEquals($expected, $result);

        // 获取若干列不指定key时不报错
        $result = Db::table('test_user')->column('username,nickname,id');
        $expected = array_column_ex($users, ['username', 'nickname', 'id']);
        $this->assertEquals($expected, $result);

        // 数组方式获取
        $result = Db::table('test_user')->column(['username', 'nickname', 'type'], 'id');
        $expected = array_column_ex($users, ['username', 'nickname', 'type', 'id'], 'id');
        $this->assertEquals($expected, $result);

        // 数组方式获取（重命名字段）
        $result = Db::table('test_user')->column(['username' => 'my_name', 'nickname'], 'id');
        $expected = array_column_ex($users, ['username' => 'my_name', 'nickname', 'id'], 'id');
        array_value_sort($result);
        array_value_sort($expected);
        $this->assertEquals($expected, $result);

        // 数组方式获取（定义表达式）
        $result = Db::table('test_user')
            ->column([
                'username' => 'my_name',
                'nickname',
                new Raw('`type`+1000 as type2'),
            ], 'id');
        $expected = array_column_ex(
            $users,
            [
                'username' => 'my_name',
                'nickname',
                'type2' => function ($value) {
                    return $value['type'] + 1000;
                },
                'id'
            ],
            'id'
        );
        array_value_sort($result);
        array_value_sort($expected);
        $this->assertEquals($expected, $result);
    }
}
