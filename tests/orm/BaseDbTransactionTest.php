<?php

declare(strict_types=1);

namespace tests\orm;

use Exception;
use tests\Base;
use think\db\ConnectionInterface;
use think\db\connector\Pgsql;
use function tests\kill_connection;
use function tests\mysql_kill_connection;
use function tests\query_connection_id;
use function tests\query_mysql_connection_id;
use think\facade\Db;
use Throwable;
use function tests\query_pgsql_connection_id;

abstract class BaseDbTransactionTest extends Base
{
    protected ConnectionInterface $db;
    protected string $dbName;

    protected function provideTestData(): array
    {
        return [
            ['id' => 1, 'type' => 9, 'username' => '1-9-a'],
            ['id' => 2, 'type' => 8, 'username' => '2-8-a'],
            ['id' => 3, 'type' => 7, 'username' => '3-7-a'],
        ];
    }

    public function setUp(): void
    {
        // Db::listen(function ($sql, $time) {
        //     echo "SQL: $sql [$time ms]\n";
        // });
        $this->db = Db::connect($this->dbName, true);
        $this->db->execute('TRUNCATE TABLE test_tran_a;');
    }

    protected function reconnect(): ConnectionInterface
    {
        return $this->db = Db::connect($this->dbName, true);
    }

    protected static function insertAll(ConnectionInterface $db, string $table, array $data): void
    {
        if ($db instanceof Pgsql) {
            foreach ($data as $datum) {
                $db->table($table)->insert($datum);
            }
        } else {
            $db->table($table)->insertAll($data);
        }
    }

    public function testTransaction()
    {
        $this->db->query('SELECT 1;');
        $this->db->table('test_tran_a')->startTrans();
        self::insertAll($this->db, 'test_tran_a', $this->provideTestData());
        $this->db->table('test_tran_a')->rollback();

        $this->assertEmpty($this->db->table('test_tran_a')->column('*'));

        $this->db->execute('TRUNCATE TABLE test_tran_a;');
        $this->db->table('test_tran_a')->startTrans();
        self::insertAll($this->db, 'test_tran_a', $this->provideTestData());
        $this->db->table('test_tran_a')->commit();
        $this->assertEquals($this->provideTestData(),  $this->db->table('test_tran_a')->column('*'));
        $this->db->table('test_tran_a')->startTrans();
        $this->db->table('test_tran_a')->where('id', '=', 2)->update([
            'username' => '2-8-b',
        ]);
        $this->db->table('test_tran_a')->commit();
        $this->assertEquals(
            '2-8-b',
            $this->db->table('test_tran_a')->where('id', '=', 2)->value('username')
        );
    }

    public function testBreakReconnect()
    {
        // 初始化配置
        $oldConfig = Db::getConfig();
        $config = $oldConfig;
        $config['connections'][$this->dbName]['break_reconnect'] = true;
        $config['connections'][$this->dbName]['break_match_str'] = [
            'query execution was interrupted',
            'no connection to the server',
        ];
        Db::setConfig($config);

        $this->reconnect();
        try {
            // 初始化数据
            self::insertAll($this->db, 'test_tran_a', $this->provideTestData());

            $cid = query_connection_id($this->db);
            kill_connection($this->dbName . '_manage', $cid);
            // 触发重连
            $this->db->table('test_tran_a')->where('id', '=', 2)->value('username');

            $newCid = query_connection_id($this->db);
            $this->assertNotEquals($cid, $newCid);
            $cid = $newCid;

            // 事务前重连
            kill_connection($this->dbName . '_manage', $cid);
            $this->db->table('test_tran_a')->startTrans();
            $this->db->table('test_tran_a')->where('id', '=', 2)->update([
                'username' => '2-8-b',
            ]);
            $this->db->table('test_tran_a')->commit();
            $newCid = query_connection_id($this->db);
            $this->assertNotEquals($cid, $newCid);
            $cid = $newCid;
            $this->assertEquals(
                '2-8-b',
                $this->db->table('test_tran_a')->where('id', '=', 2)->value('username')
            );

            // 事务中不能重连
            try {
                $this->db->table('test_tran_a')->startTrans();
                $this->db->table('test_tran_a')->where('id', '=', 2)->update([
                    'username' => '2-8-c',
                ]);
                kill_connection($this->dbName . '_manage', $cid);
                $this->db->table('test_tran_a')->where('id', '=', 3)->update([
                    'username' => '3-7-b',
                ]);
                $this->db->table('test_tran_a')->commit();
            } catch (Throwable|Exception $exception) {
                try {
                    $this->db->table('test_tran_a')->rollback();
                } catch (Exception $rollbackException) {
                    // Ignore exception
                    $this->proxyAssertMatchesRegularExpression(
                        $this->dbName === 'mysql' ? '~(server has gone away)~' : '~(no connection to the server)~',
                        $rollbackException->getMessage()
                    );
                }
                // Ignore exception
                $this->proxyAssertMatchesRegularExpression(
                    $this->dbName === 'mysql' ? '~(server has gone away)~' : '~(no connection to the server)~',
                    $exception->getMessage()
                );
            }
            // 预期应该没有发生任何更改
            $this->assertEquals(
                '2-8-b',
                $this->db->table('test_tran_a')->where('id', '=', 2)->value('username')
            );
            $this->assertEquals(
                '3-7-a',
                $this->db->table('test_tran_a')->where('id', '=', 3)->value('username')
            );
        } finally {
            Db::setConfig($oldConfig);
            $this->reconnect();
        }
    }

    public function testTransactionSavepoint()
    {
        // 初始化数据
        $oldConnect = $this->db;
        $oldConnect->query('select 1;');
        $newConnect = $this->reconnect();
        $newConnect->query('select 1;');
        self::assertNotEquals(spl_object_id($oldConnect), spl_object_id($newConnect));

        self::insertAll($newConnect, 'test_tran_a', $this->provideTestData());

        try {
            $this->db->table('test_tran_a')->transaction(function () use ($newConnect, $oldConnect) {
                // tran 1
                $newConnect->table('test_tran_a')->startTrans();
                $oldConnect->table('test_tran_a')->where('id', '=', 2)->update([
                    'username' => '2-8-c',
                ]);
                $newConnect->table('test_tran_a')->commit();
                // tran 2
                $newConnect->table('test_tran_a')->startTrans();
                $oldConnect->table('test_tran_a')->where('id', '=', 3)->update([
                    'username' => '3-7-b',
                ]);
                $newConnect->table('test_tran_a')->commit();
            });
        } finally {
            $oldConnect->close();
        }

        // 预期变化
        $this->assertEquals(
            '2-8-c',
            $newConnect->table('test_tran_a')->where('id', '=', 2)->value('username')
        );
        $this->assertEquals(
            '3-7-b',
            $newConnect->table('test_tran_a')->where('id', '=', 3)->value('username')
        );
    }

    public function testTransactionSavepointBreakReconnect()
    {
        // 初始化配置
        $oldConfig = Db::getConfig();
        $config = $oldConfig;
        $config['connections'][$this->dbName]['break_reconnect'] = true;
        $config['connections'][$this->dbName]['break_match_str'] = [
            'query execution was interrupted',
            'no connection to the server',
        ];
        Db::setConfig($config);
        // 初始化数据
        $oldConnect = $this->db;
        $oldConnect->query('select 1;');
        $newConnect = $this->reconnect();
        $newConnect->query('select 1;');
        self::assertNotEquals(spl_object_id($oldConnect->getPdo()), spl_object_id($newConnect->getPdo()));
        self::insertAll($newConnect, 'test_tran_a', $this->provideTestData());

        // 事务中不能重连
        try {
            // tran 0
            $newConnect->table('test_tran_a')->startTrans();
            $cid = query_connection_id($newConnect);
            // tran 1
            $oldConnect->table('test_tran_a')->startTrans();
            $newConnect->table('test_tran_a')->where('id', '=', 2)->update([
                'username' => '2-8-c',
            ]);
            $oldConnect->table('test_tran_a')->commit();
            // kill
            kill_connection($this->dbName . '_manage', $cid);
            // tran 2
            $oldConnect->table('test_tran_a')->startTrans();
            $newConnect->table('test_tran_a')->where('id', '=', 3)->update([
                'username' => '3-7-b',
            ]);
            $oldConnect->table('test_tran_a')->commit();
            // tran 0
            $newConnect->table('test_tran_a')->commit();
        } catch (Throwable|Exception $exception) {
            try {
                $newConnect->table('test_tran_a')->rollback();
            } catch (Exception $rollbackException) {
                // Ignore exception
                $this->proxyAssertMatchesRegularExpression(
                    $this->dbName === 'mysql' ? '~(server has gone away)~' : '~(no connection to the server)~',
                    $rollbackException->getMessage()
                );
            }
            // Ignore exception
            $this->proxyAssertMatchesRegularExpression(
                $this->dbName === 'mysql' ? '~(server has gone away)~' : '~(no connection to the server)~',
                $exception->getMessage()
            );
        } finally {
            $oldConnect->close();
        }
        // 预期应该没有发生任何更改
        $this->assertEquals(
            '2-8-a',
            $this->db->table('test_tran_a')->where('id', '=', 2)->value('username')
        );
        $this->assertEquals(
            '3-7-a',
            $this->db->table('test_tran_a')->where('id', '=', 3)->value('username')
        );
    }
}
