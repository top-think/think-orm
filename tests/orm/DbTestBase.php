<?php

declare(strict_types=1);

namespace tests\orm;

use think\db\ConnectionInterface;
use function array_column;
use function array_keys;
use function array_unique;
use function array_values;
use function tests\array_column_ex;
use function tests\array_value_sort;
use tests\Base;
use think\Collection;
use think\db\exception\DbException;
use think\db\Raw;
use think\Exception as ThinkException;
use think\facade\Db;
use function tests\pg_install_func;
use function tests\pg_reset_function;

abstract class DbTestBase extends Base
{
    public ConnectionInterface $db;
    protected static string $dbName;

    protected function provideTestData(): array
    {
        return [
            ['id' => 1, 'type' => 3, 'username' => 'qw"e"qwe', 'nickname' => 'asdasd', 'password' => '123123'],
            ['id' => 2, 'type' => 2, 'username' => 'rt"yrty', 'nickname' => 'fghfgh', 'password' => '456456'],
            ['id' => 3, 'type' => 1, 'username' => 'uiouio', 'nickname' => 'jkljkl', 'password' => '789789'],
            ['id' => 5, 'type' => 2, 'username' => 'qazqaz', 'nickname' => 'wsxwsx', 'password' => '098098'],
            ['id' => 7, 'type' => 2, 'username' => 'rfvrfv', 'nickname' => 'tgbtgb', 'password' => '765765'],
        ];
    }

    public function setUp(): void
    {
        $this->db = Db::connect($this->dbName);

        if ($this->dbName === 'pgsql') {
            pg_reset_function();
            pg_install_func();
        }
    }

    public function testInitUsers(): array
    {
        $this->db->execute('TRUNCATE TABLE test_user;');

        $userData = $this->provideTestData();
        $this->db->table('test_user')->insertAll($userData);

        return $userData;
    }

    /**
     * @depends testInitUsers
     */
    public function testColumn(array $users)
    {
        // 获取全部列
        $result = $this->db->table('test_user')->column('*', 'id');

        $this->assertCount(5, $result);
        $this->assertEquals($users, array_values($result));
        $this->assertEquals(array_column($users, 'id'), array_keys($result));

        // 获取某一个字段
        $result = $this->db->table('test_user')->column('username');
        $this->assertEquals(array_column($users, 'username'), $result);

        // 获取某字段唯一
        $result = $this->db->table('test_user')->order('type', 'desc')->column('DISTINCT type');
        $expected = array_unique(array_column($users, 'type'));
        $this->assertEquals($expected, $result);

        // 字段别名
        $result = $this->db->table('test_user')->column('username as name2');
        $expected = array_column($users, 'username');
        $this->assertEquals($expected, $result);

        // 表别名
        $result = $this->db->table('test_user')->alias('test2')->column('test2.username');
        $expected = array_column($users, 'username');
        $this->assertEquals($expected, $result);

        // 获取若干列
        $result = $this->db->table('test_user')->column('username,nickname', 'id');
        $expected = array_column_ex($users, ['username', 'nickname', 'id'], 'id');
        $this->assertEquals($expected, $result);

        // 获取若干列不指定key时不报错
        $result = $this->db->table('test_user')->column('username,nickname,id');
        $expected = array_column_ex($users, ['username', 'nickname', 'id']);
        $this->assertEquals($expected, $result);

        // 数组方式获取
        $result = $this->db->table('test_user')->column(['username', 'nickname', 'type'], 'id');
        $expected = array_column_ex($users, ['username', 'nickname', 'type', 'id'], 'id');
        $this->assertEquals($expected, $result);

        // 数组方式获取（单字段）
        $result = $this->db->table('test_user')->column(['type'], 'id');
        $expected = array_column($users, 'type', 'id');
        $this->assertEquals($expected, $result);

        // 数组方式获取（重命名字段）
        $result = $this->db->table('test_user')->column(['username' => 'my_name', 'nickname'], 'id');
        $expected = array_column_ex($users, ['username' => 'my_name', 'nickname', 'id'], 'id');
        array_value_sort($result);
        array_value_sort($expected);
        $this->assertEquals($expected, $result);

        // 数组方式获取（定义表达式）
        $result = $this->db->table('test_user')
            ->column([
                'username' => 'my_name',
                'nickname',
                new Raw('type+1000 as type2'),
            ], 'id');
        $expected = array_column_ex(
            $users,
            [
                'username' => 'my_name',
                'nickname',
                'type2' => function ($value) {
                    return $value['type'] + 1000;
                },
                'id',
            ],
            'id'
        );
        array_value_sort($result);
        array_value_sort($expected);
        $this->assertEquals($expected, $result);
    }

    /**
     * @depends testInitUsers
     */
    public function testWhereIn(array $users)
    {
        $sqlLogs = [];
        Db::listen(function ($sql) use (&$sqlLogs) {
            $sqlLogs[] = $sql;
        });

        $expected = Collection::make($users)->whereIn('type', [1, 3])->values()->toArray();
        $result = $this->db->table('test_user')->whereIn('type', [1, 3])->column('*');
        $this->assertEquals($expected, $result);

        $expected = Collection::make($users)->whereIn('type', [1])->values()->toArray();
        $result = $this->db->table('test_user')->whereIn('type', [1])->column('*');
        $this->assertEquals($expected, $result);

        $expected = Collection::make($users)->whereIn('type', [1, ''])->values()->toArray();
        $result = $this->db->table('test_user')->whereIn('type', [1, ''])->column('*');
        $this->assertEquals($expected, $result);

        $result = $this->db->table('test_user')->whereIn('type', [])->column('*');
        $this->assertEquals([], $result);

        $expected = Collection::make($users)->whereNotIn('type', [1, 3])->values()->toArray();
        $result = $this->db->table('test_user')->whereNotIn('type', [1, 3])->column('*');
        $this->assertEquals($expected, $result);

        $expected = Collection::make($users)->values()->toArray();
        $result = $this->db->table('test_user')->whereNotIn('type', [])->column('*');
        $this->assertEquals($expected, $result);

        // 合并多余空格，替换 "`" 是为了同时兼容 pg 与 mysql
        $sqlLogs = array_map(static fn ($str) => preg_replace(['#\s{2,}#', '~`~'], [' ', ''], $str), $sqlLogs);

        $this->assertEquals([
            'SELECT * FROM test_user WHERE type IN (1,3)',
            'SELECT * FROM test_user WHERE type = 1',
            'SELECT * FROM test_user WHERE type IN (1,0)',
            'SELECT * FROM test_user WHERE 0 = 1',
            'SELECT * FROM test_user WHERE type NOT IN (1,3)',
            'SELECT * FROM test_user WHERE 1 = 1',
        ], $sqlLogs);
    }

    public function testException()
    {
        $this->expectException(DbException::class);

        try {
            $this->db->query('wrong syntax');
        } catch (DbException $exception) {
            $this->assertInstanceOf(ThinkException::class, $exception);

            throw $exception;
        }
    }
}
