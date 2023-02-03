<?php

declare(strict_types=1);

namespace tests\orm;

use Exception;
use tests\Base;
use function tests\mysql_kill_connection;
use function tests\query_mysql_connection_id;
use think\facade\Db;
use Throwable;

class DbTransactionTest extends Base
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_tran_a`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_tran_a` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `type` tinyint(4) NOT NULL DEFAULT '0',
     `username` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_tran_a`;');
        self::$testData = [
            ['id' => 1, 'type' => 9, 'username' => '1-9-a'],
            ['id' => 2, 'type' => 8, 'username' => '2-8-a'],
            ['id' => 3, 'type' => 7, 'username' => '3-7-a'],
        ];
    }

    public function testTransaction()
    {
        $testData = self::$testData;
        $connect = Db::connect();

        $connect->table('test_tran_a')->startTrans();
        $connect->table('test_tran_a')->insertAll($testData);
        $connect->table('test_tran_a')->rollback();

        $this->assertEmpty($connect->table('test_tran_a')->column('*'));

        $connect->execute('TRUNCATE TABLE `test_tran_a`;');
        $connect->table('test_tran_a')->startTrans();
        $connect->table('test_tran_a')->insertAll($testData);
        $connect->table('test_tran_a')->commit();
        $this->assertEquals($testData, $connect->table('test_tran_a')->column('*'));
        $connect->table('test_tran_a')->startTrans();
        $connect->table('test_tran_a')->where('id', '=', 2)->update([
            'username' => '2-8-b',
        ]);
        $connect->table('test_tran_a')->commit();
        $this->assertEquals(
            '2-8-b',
            $connect->table('test_tran_a')->where('id', '=', 2)->value('username')
        );
    }

    public function testBreakReconnect()
    {
        $testData = self::$testData;
        // 初始化配置
        $config = Db::getConfig();
        $config['connections']['mysql']['break_reconnect'] = true;
        $config['connections']['mysql']['break_match_str'] = [
            'query execution was interrupted',
        ];
        Db::setConfig($config);
        // 初始化数据
        $connect = Db::connect(null, true);
        $connect->table('test_tran_a')->insertAll($testData);

        $cid = query_mysql_connection_id($connect);
        mysql_kill_connection('mysql_manage', $cid);
        // 触发重连
        $connect->table('test_tran_a')->where('id', '=', 2)->value('username');

        $newCid = query_mysql_connection_id($connect);
        $this->assertNotEquals($cid, $newCid);
        $cid = $newCid;

        // 事务前重连
        mysql_kill_connection('mysql_manage', $cid);
        Db::table('test_tran_a')->startTrans();
        $connect->table('test_tran_a')->where('id', '=', 2)->update([
            'username' => '2-8-b',
        ]);
        Db::table('test_tran_a')->commit();
        $newCid = query_mysql_connection_id($connect);
        $this->assertNotEquals($cid, $newCid);
        $cid = $newCid;
        $this->assertEquals(
            '2-8-b',
            Db::table('test_tran_a')->where('id', '=', 2)->value('username')
        );

        // 事务中不能重连
        try {
            Db::table('test_tran_a')->startTrans();
            $connect->table('test_tran_a')->where('id', '=', 2)->update([
                'username' => '2-8-c',
            ]);
            mysql_kill_connection('mysql_manage', $cid);
            $connect->table('test_tran_a')->where('id', '=', 3)->update([
                'username' => '3-7-b',
            ]);
            Db::table('test_tran_a')->commit();
        } catch (Throwable|Exception $exception) {
            try {
                Db::table('test_tran_a')->rollback();
            } catch (Exception $rollbackException) {
                // Ignore exception
                $this->proxyAssertMatchesRegularExpression(
                    '~(server has gone away)~',
                    $rollbackException->getMessage()
                );
            }
            // Ignore exception
            $this->proxyAssertMatchesRegularExpression(
                '~(server has gone away)~',
                $exception->getMessage()
            );
        }
        // 预期应该没有发生任何更改
        $this->assertEquals(
            '2-8-b',
            Db::table('test_tran_a')->where('id', '=', 2)->value('username')
        );
        $this->assertEquals(
            '3-7-a',
            Db::table('test_tran_a')->where('id', '=', 3)->value('username')
        );
    }

    public function testTransactionSavepoint()
    {
        $testData = self::$testData;
        // 初始化数据
        $connect = Db::connect(null, true);
        $connect->table('test_tran_a')->insertAll($testData);

        Db::table('test_tran_a')->transaction(function () use ($connect) {
            $cid = query_mysql_connection_id($connect);
            // tran 1
            Db::table('test_tran_a')->startTrans();
            $connect->table('test_tran_a')->where('id', '=', 2)->update([
                'username' => '2-8-c',
            ]);
            Db::table('test_tran_a')->commit();
            // tran 2
            Db::table('test_tran_a')->startTrans();
            $connect->table('test_tran_a')->where('id', '=', 3)->update([
                'username' => '3-7-b',
            ]);
            Db::table('test_tran_a')->commit();
        });

        // 预期变化
        $this->assertEquals(
            '2-8-c',
            Db::table('test_tran_a')->where('id', '=', 2)->value('username')
        );
        $this->assertEquals(
            '3-7-b',
            Db::table('test_tran_a')->where('id', '=', 3)->value('username')
        );
    }

    public function testTransactionSavepointBreakReconnect()
    {
        $testData = self::$testData;
        // 初始化配置
        $config = Db::getConfig();
        $config['connections']['mysql']['break_reconnect'] = true;
        $config['connections']['mysql']['break_match_str'] = [
            'query execution was interrupted',
        ];
        Db::setConfig($config);
        // 初始化数据
        $connect = Db::connect(null, true);
        $connect->table('test_tran_a')->insertAll($testData);

        // 事务中不能重连
        try {
            // tran 0
            Db::table('test_tran_a')->startTrans();
            $cid = query_mysql_connection_id($connect);
            // tran 1
            Db::table('test_tran_a')->startTrans();
            $connect->table('test_tran_a')->where('id', '=', 2)->update([
                'username' => '2-8-c',
            ]);
            Db::table('test_tran_a')->commit();
            // kill
            mysql_kill_connection('mysql_manage', $cid);
            // tran 2
            Db::table('test_tran_a')->startTrans();
            $connect->table('test_tran_a')->where('id', '=', 3)->update([
                'username' => '3-7-b',
            ]);
            Db::table('test_tran_a')->commit();
            // tran 0
            Db::table('test_tran_a')->commit();
        } catch (Throwable|Exception $exception) {
            try {
                Db::table('test_tran_a')->rollback();
            } catch (Exception $rollbackException) {
                // Ignore exception
                $this->proxyAssertMatchesRegularExpression(
                    '~(server has gone away)~',
                    $rollbackException->getMessage()
                );
            }
            // Ignore exception
            $this->proxyAssertMatchesRegularExpression(
                '~(server has gone away)~',
                $exception->getMessage()
            );
        }
        // 预期应该没有发生任何更改
        $this->assertEquals(
            '2-8-a',
            Db::table('test_tran_a')->where('id', '=', 2)->value('username')
        );
        $this->assertEquals(
            '3-7-a',
            Db::table('test_tran_a')->where('id', '=', 3)->value('username')
        );
    }
}
