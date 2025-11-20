<?php

namespace tests\orm;

use PDO;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use think\DbManager;
use think\db\Connection;
use think\db\ConnectionInterface;
use think\db\connector\Mysql;
use think\db\exception\BindParamException;
use think\db\exception\DbException;
use think\db\exception\PDOException;
use think\facade\Db;

class ConnectionTest extends TestCase
{
    protected ConnectionInterface $connection;
    protected array $config;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_connection`;');
        Db::execute("CREATE TABLE `test_connection` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL DEFAULT '',
          `value` varchar(255) NOT NULL DEFAULT '',
          `status` tinyint(1) NOT NULL DEFAULT 1,
          `create_time` datetime DEFAULT NULL,
          `update_time` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
    }

    public function setUp(): void
    {
        $this->config = [
            'type' => 'mysql',
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => 'test_',
            'debug' => false,
            'fields_cache' => false,
        ];

        $this->connection = new Mysql($this->config);
        $dbManager = new DbManager();
        $this->connection->setDb($dbManager);

        Db::execute('TRUNCATE TABLE `test_connection`;');
    }

    public function testConnectionCreation(): void
    {
        $this->assertInstanceOf(ConnectionInterface::class, $this->connection);
        $this->assertInstanceOf(Mysql::class, $this->connection);
    }

    public function testGetConfig(): void
    {
        $config = $this->connection->getConfig();
        
        $this->assertEquals('mysql', $config['type']);
        $this->assertEquals('utf8', $config['charset']);
        $this->assertEquals('test_', $config['prefix']);
    }

    public function testGetConfigSpecific(): void
    {
        $type = $this->connection->getConfig('type');
        $charset = $this->connection->getConfig('charset');
        $nonexistent = $this->connection->getConfig('nonexistent');
        
        $this->assertEquals('mysql', $type);
        $this->assertEquals('utf8', $charset);
        $this->assertNull($nonexistent); // getConfig returns null for non-existent keys
    }

    public function testSetCacheInterface(): void
    {
        // Skip this test if CacheInterface is not available
        if (!interface_exists('Psr\SimpleCache\CacheInterface')) {
            $this->markTestSkipped('CacheInterface not available');
        }
        
        $cache = $this->createMock(CacheInterface::class);
        
        $this->connection->setCache($cache);
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testExecuteSelect(): void
    {
        // Insert test data
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('test1', 'value1', 1), ('test2', 'value2', 0)"
        );

        $result = $this->connection->query("SELECT * FROM `test_connection` WHERE `status` = 1");
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('test1', $result[0]['name']);
        $this->assertEquals('value1', $result[0]['value']);
    }

    public function testExecuteInsert(): void
    {
        $result = $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('insert_test', 'insert_value', 1)"
        );
        
        $this->assertEquals(1, $result); // Should return affected rows
        
        // Verify insertion
        $rows = $this->connection->query("SELECT COUNT(*) as count FROM `test_connection` WHERE `name` = 'insert_test'");
        $this->assertEquals(1, $rows[0]['count']);
    }

    public function testExecuteUpdate(): void
    {
        // Insert test data first
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('update_test', 'old_value', 1)"
        );

        $result = $this->connection->execute(
            "UPDATE `test_connection` SET `value` = 'new_value' WHERE `name` = 'update_test'"
        );
        
        $this->assertEquals(1, $result); // Should return affected rows
        
        // Verify update
        $rows = $this->connection->query("SELECT `value` FROM `test_connection` WHERE `name` = 'update_test'");
        $this->assertEquals('new_value', $rows[0]['value']);
    }

    public function testExecuteDelete(): void
    {
        // Insert test data first
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('delete_test', 'delete_value', 1)"
        );

        $result = $this->connection->execute(
            "DELETE FROM `test_connection` WHERE `name` = 'delete_test'"
        );
        
        $this->assertEquals(1, $result); // Should return affected rows
        
        // Verify deletion
        $rows = $this->connection->query("SELECT COUNT(*) as count FROM `test_connection` WHERE `name` = 'delete_test'");
        $this->assertEquals(0, $rows[0]['count']);
    }

    public function testTransactionCommit(): void
    {
        $this->connection->startTrans();
        
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('trans_commit', 'trans_value', 1)"
        );
        
        $this->connection->commit();
        
        // Verify data was committed
        $rows = $this->connection->query("SELECT COUNT(*) as count FROM `test_connection` WHERE `name` = 'trans_commit'");
        $this->assertEquals(1, $rows[0]['count']);
    }

    public function testTransactionRollback(): void
    {
        $this->connection->startTrans();
        
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('trans_rollback', 'trans_value', 1)"
        );
        
        $this->connection->rollback();
        
        // Verify data was rolled back
        $rows = $this->connection->query("SELECT COUNT(*) as count FROM `test_connection` WHERE `name` = 'trans_rollback'");
        $this->assertEquals(0, $rows[0]['count']);
    }

    public function testNestedTransactions(): void
    {
        $this->connection->startTrans();
        $this->connection->startTrans(); // Nested transaction
        
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('nested_trans', 'nested_value', 1)"
        );
        
        $this->connection->rollback(); // Rollback nested
        $this->connection->commit(); // Commit outer
        
        // Verify behavior depends on implementation, but should not throw exception
        $this->assertTrue(true);
    }

    public function testParameterBinding(): void
    {
        $result = $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES (?, ?, ?)",
            ['param_test', 'param_value', 1]
        );
        
        $this->assertEquals(1, $result);
        
        // Verify insertion with parameter binding in select
        $rows = $this->connection->query(
            "SELECT * FROM `test_connection` WHERE `name` = ? AND `status` = ?",
            ['param_test', 1]
        );
        
        $this->assertCount(1, $rows);
        $this->assertEquals('param_test', $rows[0]['name']);
        $this->assertEquals('param_value', $rows[0]['value']);
    }

    public function testGetLastInsertId(): void
    {
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES ('lastid_test', 'lastid_value', 1)"
        );
        
        // For MySQL, we can use the raw PDO method
        $pdo = $this->connection->getPdo();
        $lastId = $pdo->lastInsertId();
        
        $this->assertIsString($lastId);
        $this->assertGreaterThan(0, (int)$lastId);
    }

    public function testGetNumRows(): void
    {
        // Insert multiple rows
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`, `status`) VALUES 
            ('rows1', 'value1', 1), 
            ('rows2', 'value2', 1),
            ('rows3', 'value3', 0)"
        );

        $this->connection->query("SELECT * FROM `test_connection` WHERE `status` = 1");
        $numRows = $this->connection->getNumRows();
        
        $this->assertEquals(2, $numRows);
    }

    public function testGetFields(): void
    {
        $fields = $this->connection->getFields('test_connection');
        
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('value', $fields);
        $this->assertArrayHasKey('status', $fields);
    }

    public function testGetTableInfo(): void
    {
        $tableInfo = $this->connection->getTableInfo('test_connection');
        
        $this->assertIsArray($tableInfo);
        $this->assertArrayHasKey('fields', $tableInfo);
        $this->assertArrayHasKey('type', $tableInfo);
    }

    public function testClose(): void
    {
        // Should not throw exception
        $this->connection->close();
        $this->assertTrue(true);
    }

    public function testInvalidQuery(): void
    {
        $this->expectException(PDOException::class);
        
        $this->connection->query("SELECT * FROM nonexistent_table");
    }

    public function testInvalidExecute(): void
    {
        $this->expectException(PDOException::class);
        
        $this->connection->execute("INSERT INTO nonexistent_table (field) VALUES ('value')");
    }

    public function testParameterBindingErrors(): void
    {
        // Test parameter binding error - this might throw different exceptions based on PDO behavior
        try {
            $this->connection->execute(
                "INSERT INTO `test_connection` (`name`) VALUES (?, ?)", // Two placeholders
                ['param1'] // Only one parameter - this should cause an error
            );
            $this->fail('Expected an exception to be thrown');
        } catch (\Exception $e) {
            // Accept various types of exceptions that could occur during parameter binding
            $this->assertTrue(
                $e instanceof BindParamException || 
                $e instanceof \TypeError || 
                $e instanceof \PDOException ||
                $e instanceof \think\db\exception\PDOException, // Include the ThinkORM PDOException
                'Expected binding-related exception, got: ' . get_class($e)
            );
        }
    }

    public function testConnectionReuse(): void
    {
        // Execute multiple queries to test connection reuse
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`) VALUES ('reuse1', 'value1')"
        );
        
        $this->connection->execute(
            "INSERT INTO `test_connection` (`name`, `value`) VALUES ('reuse2', 'value2')"
        );
        
        $result = $this->connection->query(
            "SELECT COUNT(*) as count FROM `test_connection` WHERE `name` LIKE 'reuse%'"
        );
        
        $this->assertEquals(2, $result[0]['count']);
    }

    public function testPreparedStatementReuse(): void
    {
        // Execute same query multiple times with different parameters
        for ($i = 1; $i <= 3; $i++) {
            $this->connection->execute(
                "INSERT INTO `test_connection` (`name`, `value`) VALUES (?, ?)",
                ["prepared_$i", "value_$i"]
            );
        }
        
        $result = $this->connection->query(
            "SELECT COUNT(*) as count FROM `test_connection` WHERE `name` LIKE 'prepared_%'"
        );
        
        $this->assertEquals(3, $result[0]['count']);
    }

    public function testGetTables(): void
    {
        $tables = $this->connection->getTables();
        
        $this->assertIsArray($tables);
        $this->assertGreaterThan(0, count($tables));
        
        // Check if our test table exists (it might be in different formats)
        $found = false;
        foreach ($tables as $table) {
            if (is_array($table)) {
                $tableName = $table['Name'] ?? $table['TABLE_NAME'] ?? '';
            } else {
                $tableName = $table;
            }
            if (str_contains($tableName, 'test_connection')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Test table should be found in table list');
    }

    public function testQueryStartTime(): void
    {
        $startTime = microtime(true);
        
        $this->connection->query("SELECT SLEEP(0.01)"); // Small delay
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should take at least 0.01 seconds
        $this->assertGreaterThanOrEqual(0.01, $duration);
    }
}