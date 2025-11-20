<?php

namespace tests\orm;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use think\DbManager;
use think\db\ConnectionInterface;
use think\db\Raw;
use think\facade\Db;

class DbManagerTest extends TestCase
{
    protected DbManager $dbManager;

    public function setUp(): void
    {
        $this->dbManager = new DbManager();
        $this->dbManager->setConfig([
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'type' => 'mysql',
                    'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
                    'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
                    'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
                    'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
                    'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
                    'charset' => 'utf8',
                    'prefix' => 'test_',
                ],
                'mysql2' => [
                    'type' => 'mysql',
                    'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
                    'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
                    'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
                    'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
                    'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
                    'charset' => 'utf8',
                    'prefix' => 'test2_',
                ],
                'sqlite' => [
                    'type' => 'sqlite',
                    'database' => ':memory:',
                ],
            ],
            'auto_timestamp' => true,
            'datetime_format' => 'Y-m-d H:i:s',
        ]);
    }

    public function testConstructor(): void
    {
        $manager = new DbManager();
        $this->assertInstanceOf(DbManager::class, $manager);
    }

    public function testSetAndGetConfig(): void
    {
        $config = [
            'default' => 'mysql',
            'connections' => [
                'mysql' => ['type' => 'mysql', 'hostname' => 'localhost'],
            ],
        ];

        $this->dbManager->setConfig($config);
        
        $this->assertEquals($config, $this->dbManager->getConfig());
        $this->assertEquals('mysql', $this->dbManager->getConfig('default'));
        $this->assertEquals('default_value', $this->dbManager->getConfig('nonexistent', 'default_value'));
        $this->assertNull($this->dbManager->getConfig('nonexistent'));
    }

    public function testConnectWithDefaultConnection(): void
    {
        $connection = $this->dbManager->connect();
        
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectWithSpecificConnection(): void
    {
        $connection = $this->dbManager->connect('mysql2');
        
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectWithArrayConfig(): void
    {
        $config = [
            'type' => 'mysql',
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            'charset' => 'utf8',
        ];

        $connection = $this->dbManager->connect($config);
        
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testConnectWithForceReconnection(): void
    {
        $connection1 = $this->dbManager->connect('mysql');
        $connection2 = $this->dbManager->connect('mysql');
        $connection3 = $this->dbManager->connect('mysql', true);

        // Same instance without force
        $this->assertSame($connection1, $connection2);
        
        // Different instance with force
        $this->assertNotSame($connection1, $connection3);
        $this->assertInstanceOf(ConnectionInterface::class, $connection3);
    }

    public function testConnectWithInvalidConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined db config:invalid');
        
        $this->dbManager->connect('invalid');
    }

    public function testConnectionCaching(): void
    {
        $connection1 = $this->dbManager->connect('mysql');
        $connection2 = $this->dbManager->connect('mysql');
        
        // Should return the same cached instance
        $this->assertSame($connection1, $connection2);
    }

    public function testMultipleConnectionTypes(): void
    {
        $mysqlConnection = $this->dbManager->connect('mysql');
        $sqliteConnection = $this->dbManager->connect('sqlite');
        
        $this->assertInstanceOf(ConnectionInterface::class, $mysqlConnection);
        $this->assertInstanceOf(ConnectionInterface::class, $sqliteConnection);
        $this->assertNotSame($mysqlConnection, $sqliteConnection);
    }

    public function testSetCache(): void
    {
        // Skip this test if CacheInterface is not available
        if (!interface_exists('Psr\SimpleCache\CacheInterface')) {
            $this->markTestSkipped('CacheInterface not available');
        }
        
        $cache = $this->createMock(CacheInterface::class);
        
        $this->dbManager->setCache($cache);
        
        // Create a connection to test if cache is properly set
        $connection = $this->dbManager->connect('mysql');
        $this->assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testSetLogWithLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('log')
               ->with('sql', 'SELECT * FROM users');
        
        $this->dbManager->setLog($logger);
        $this->dbManager->log('SELECT * FROM users', 'sql');
    }

    public function testSetLogWithClosure(): void
    {
        $logCalled = false;
        $loggedType = '';
        $loggedMessage = '';
        
        $closure = function($type, $message) use (&$logCalled, &$loggedType, &$loggedMessage) {
            $logCalled = true;
            $loggedType = $type;
            $loggedMessage = $message;
        };
        
        $this->dbManager->setLog($closure);
        $this->dbManager->log('SELECT * FROM users', 'sql');
        
        $this->assertTrue($logCalled);
        $this->assertEquals('sql', $loggedType);
        $this->assertEquals('SELECT * FROM users', $loggedMessage);
    }

    public function testLogWithDefaultType(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
               ->method('log')
               ->with('sql', 'SELECT * FROM users');
        
        $this->dbManager->setLog($logger);
        $this->dbManager->log('SELECT * FROM users'); // No type specified, should default to 'sql'
    }

    public function testLogWithoutLogger(): void
    {
        // Should not throw exception when no logger is set
        $this->dbManager->log('SELECT * FROM users');
        $this->assertTrue(true); // Assert that no exception was thrown
    }

    public function testGetDbLogDeprecated(): void
    {
        $result = $this->dbManager->getDbLog();
        $this->assertEquals([], $result);
        
        $result = $this->dbManager->getDbLog(true);
        $this->assertEquals([], $result);
    }

    public function testRawExpression(): void
    {
        $raw = $this->dbManager->raw('NOW()');
        
        $this->assertInstanceOf(Raw::class, $raw);
    }

    public function testMagicCallMethods(): void
    {
        // Test magic call to query methods
        $query = $this->dbManager->table('test_users');
        $this->assertInstanceOf(\think\db\Query::class, $query);
        
        $query = $this->dbManager->name('test_users');
        $this->assertInstanceOf(\think\db\Query::class, $query);
    }

    public function testTransactionMethods(): void
    {
        // Test transaction methods through magic call
        $this->dbManager->startTrans();
        $this->assertTrue(true); // Assert no exception thrown
        
        $this->dbManager->rollback();
        $this->assertTrue(true); // Assert no exception thrown
    }

    public function testConnectionTypeResolution(): void
    {
        // Test that connection type is resolved correctly for different database types
        $mysqlConnection = $this->dbManager->connect('mysql');
        $this->assertInstanceOf(ConnectionInterface::class, $mysqlConnection);
        
        $sqliteConnection = $this->dbManager->connect('sqlite');
        $this->assertInstanceOf(ConnectionInterface::class, $sqliteConnection);
        
        // Verify they are different instances
        $this->assertNotSame($mysqlConnection, $sqliteConnection);
    }

    public function testArrayConfigHashing(): void
    {
        $config1 = ['type' => 'mysql', 'hostname' => 'localhost'];
        $config2 = ['type' => 'mysql', 'hostname' => 'localhost'];
        $config3 = ['type' => 'mysql', 'hostname' => '127.0.0.1'];

        $connection1 = $this->dbManager->connect($config1);
        $connection2 = $this->dbManager->connect($config2);
        $connection3 = $this->dbManager->connect($config3);

        // Same config should return same instance
        $this->assertSame($connection1, $connection2);
        
        // Different config should return different instance
        $this->assertNotSame($connection1, $connection3);
    }

    public function testTriggerSql(): void
    {
        // Test the triggerSql method (currently empty but should not throw exception)
        $this->dbManager->triggerSql();
        $this->assertTrue(true); // Assert no exception thrown
    }
}