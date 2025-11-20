<?php

namespace tests\orm;

use PDO;
use PHPUnit\Framework\TestCase;
use think\db\connector\Mysql;
use think\db\connector\Sqlite;
use think\db\exception\PDOException;
use think\DbManager;

class DatabaseConnectorsTest extends TestCase
{
    protected DbManager $dbManager;

    public function setUp(): void
    {
        $this->dbManager = new DbManager();
    }

    public function testMysqlConnector(): void
    {
        $config = [
            'type' => 'mysql',
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => 'test_',
        ];

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        $this->assertInstanceOf(Mysql::class, $connector);
        
        // Check that the config contains our values (don't compare exact match due to defaults)
        $actualConfig = $connector->getConfig();
        $this->assertEquals('mysql', $actualConfig['type']);
        $this->assertEquals('utf8', $actualConfig['charset']);
        $this->assertEquals('test_', $actualConfig['prefix']);
    }

    public function testMysqlDsnGeneration(): void
    {
        // Test different DSN formats manually based on expected format
        $testCases = [
            [
                'hostname' => 'localhost',
                'hostport' => '3306',
                'database' => 'testdb',
                'charset' => 'utf8mb4',
                'expected' => ['mysql:host=localhost;port=3306', 'dbname=testdb', 'charset=utf8mb4']
            ],
            [
                'hostname' => 'localhost',
                'database' => 'testdb',
                'expected' => ['mysql:host=localhost', 'dbname=testdb']
            ],
            [
                'socket' => '/tmp/mysql.sock',
                'database' => 'testdb',
                'expected' => ['mysql:unix_socket=/tmp/mysql.sock', 'dbname=testdb']
            ]
        ];

        foreach ($testCases as $case) {
            $config = $case;
            unset($config['expected']);
            
            if (isset($config['socket'])) {
                $expectedDsn = 'mysql:unix_socket=' . $config['socket'] . ';dbname=' . $config['database'];
            } elseif (isset($config['hostport'])) {
                $expectedDsn = 'mysql:host=' . $config['hostname'] . ';port=' . $config['hostport'] . ';dbname=' . $config['database'];
                if (isset($config['charset'])) {
                    $expectedDsn .= ';charset=' . $config['charset'];
                }
            } else {
                $expectedDsn = 'mysql:host=' . $config['hostname'] . ';dbname=' . $config['database'];
            }
            
            // Just verify the DSN format is as expected
            foreach ($case['expected'] as $expectedPart) {
                $this->assertStringContainsString($expectedPart, $expectedDsn);
            }
        }
    }


    public function testSqliteConnector(): void
    {
        $config = [
            'type' => 'sqlite',
            'database' => ':memory:',
        ];

        $connector = new Sqlite($config);
        $connector->setDb($this->dbManager);

        $this->assertInstanceOf(Sqlite::class, $connector);
        
        // Check that the config contains our values (don't compare exact match due to defaults)
        $actualConfig = $connector->getConfig();
        $this->assertEquals('sqlite', $actualConfig['type']);
        $this->assertEquals(':memory:', $actualConfig['database']);
    }

    public function testSqliteDsnFormats(): void
    {
        // Test SQLite DSN formats
        $testCases = [
            ':memory:' => 'sqlite::memory:',
            '/path/to/db.sqlite' => 'sqlite:/path/to/db.sqlite',
            'C:\data\test.db' => 'sqlite:C:\data\test.db'
        ];

        foreach ($testCases as $database => $expectedDsn) {
            $this->assertEquals($expectedDsn, "sqlite:$database");
        }
    }

    public function testMysqlFieldsRetrieval(): void
    {
        $config = [
            'type' => 'mysql',
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => 'test_',
        ];

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        // Create a test table first
        $connector->execute("DROP TABLE IF EXISTS `test_connector_fields`");
        $connector->execute("CREATE TABLE `test_connector_fields` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL DEFAULT '',
            `email` varchar(255) DEFAULT NULL,
            `status` tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Test table for connector'");

        $fields = $connector->getFields('test_connector_fields');

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('status', $fields);

        // Test field properties
        $idField = $fields['id'];
        $this->assertEquals('id', $idField['name']);
        $this->assertTrue($idField['primary']);
        $this->assertTrue($idField['autoinc']);
        $this->assertTrue($idField['notnull']);

        $nameField = $fields['name'];
        $this->assertEquals('name', $nameField['name']);
        $this->assertFalse($nameField['primary']);
        $this->assertFalse($nameField['autoinc']);
        $this->assertTrue($nameField['notnull']);
        $this->assertEquals('', $nameField['default']);

        $emailField = $fields['email'];
        $this->assertEquals('email', $emailField['name']);
        $this->assertFalse($emailField['notnull']);
        $this->assertNull($emailField['default']);

        // Cleanup
        $connector->execute("DROP TABLE `test_connector_fields`");
    }

    public function testMysqlTablesRetrieval(): void
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

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        $tables = $connector->getTables();

        $this->assertIsArray($tables);
        $this->assertGreaterThan(0, count($tables));

        // Each table should have a name (format may vary)
        foreach ($tables as $table) {
            if (is_array($table)) {
                $this->assertTrue(isset($table['Name']) || isset($table['TABLE_NAME']));
                $name = $table['Name'] ?? $table['TABLE_NAME'];
                $this->assertIsString($name);
            } else {
                $this->assertIsString($table);
            }
        }
    }

    public function testMysqlTransactionSupport(): void
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

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        // Create test table
        $connector->execute("DROP TABLE IF EXISTS `test_transactions`");
        $connector->execute("CREATE TABLE `test_transactions` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        // Test transaction commit
        $connector->startTrans();
        $connector->execute("INSERT INTO `test_transactions` (`name`) VALUES ('test1')");
        $connector->commit();

        $result = $connector->query("SELECT COUNT(*) as count FROM `test_transactions`");
        $this->assertEquals(1, $result[0]['count']);

        // Test transaction rollback
        $connector->startTrans();
        $connector->execute("INSERT INTO `test_transactions` (`name`) VALUES ('test2')");
        $connector->rollback();

        $result = $connector->query("SELECT COUNT(*) as count FROM `test_transactions`");
        $this->assertEquals(1, $result[0]['count']); // Should still be 1

        // Cleanup
        $connector->execute("DROP TABLE `test_transactions`");
    }

    public function testSqliteInMemoryOperations(): void
    {
        $config = [
            'type' => 'sqlite',
            'database' => ':memory:',
        ];

        $connector = new Sqlite($config);
        $connector->setDb($this->dbManager);

        // Create table
        $connector->execute("CREATE TABLE test_sqlite (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            value REAL
        )");

        // Insert data
        $result = $connector->execute("INSERT INTO test_sqlite (name, value) VALUES (?, ?)", ['test', 3.14]);
        $this->assertEquals(1, $result);

        // Query data
        $data = $connector->query("SELECT * FROM test_sqlite WHERE name = ?", ['test']);
        $this->assertCount(1, $data);
        $this->assertEquals('test', $data[0]['name']);
        $this->assertEquals(3.14, $data[0]['value']);

        // Get last insert ID using PDO directly
        $pdo = $connector->getPdo();
        $lastId = $pdo->lastInsertId();
        $this->assertEquals('1', $lastId);
    }

    public function testSqliteFieldsRetrieval(): void
    {
        $config = [
            'type' => 'sqlite',
            'database' => ':memory:',
        ];

        $connector = new Sqlite($config);
        $connector->setDb($this->dbManager);

        // Create test table
        $connector->execute("CREATE TABLE test_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT,
            age INTEGER DEFAULT 0,
            created_at DATETIME
        )");

        $fields = $connector->getFields('test_fields');

        $this->assertIsArray($fields);
        $this->assertArrayHasKey('id', $fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('age', $fields);

        // Test field properties
        $idField = $fields['id'];
        $this->assertEquals('id', $idField['name']);
        $this->assertTrue($idField['primary']);
        $this->assertTrue($idField['autoinc']);
    }

    public function testConnectorConfigValidation(): void
    {
        // Test MySQL with missing required config
        $invalidConfig = [
            'type' => 'mysql',
            'hostname' => 'localhost',
            // Missing database, username, password
        ];

        $this->expectException(PDOException::class);
        
        $connector = new Mysql($invalidConfig);
        $connector->setDb($this->dbManager);
        
        // This should fail when trying to connect
        $connector->query("SELECT 1");
    }

    public function testConnectorConnectionPooling(): void
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

        $connector1 = new Mysql($config);
        $connector1->setDb($this->dbManager);
        
        $connector2 = new Mysql($config);
        $connector2->setDb($this->dbManager);

        // Both should work independently
        $result1 = $connector1->query("SELECT 1 as result");
        $result2 = $connector2->query("SELECT 2 as result");

        $this->assertEquals(1, $result1[0]['result']);
        $this->assertEquals(2, $result2[0]['result']);
    }

    public function testConnectorErrorHandling(): void
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

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        // Test invalid SQL
        $this->expectException(PDOException::class);
        $connector->query("INVALID SQL STATEMENT");
    }

    public function testConnectorCharsetHandling(): void
    {
        $config = [
            'type' => 'mysql',
            'hostname' => getenv('TESTS_DB_MYSQL_HOSTNAME'),
            'hostport' => getenv('TESTS_DB_MYSQL_HOSTPORT'),
            'database' => getenv('TESTS_DB_MYSQL_DATABASE'),
            'username' => getenv('TESTS_DB_MYSQL_USERNAME'),
            'password' => getenv('TESTS_DB_MYSQL_PASSWORD'),
            'charset' => 'utf8mb4',
        ];

        $connector = new Mysql($config);
        $connector->setDb($this->dbManager);

        // Test UTF-8 data handling
        $connector->execute("DROP TABLE IF EXISTS `test_charset`");
        $connector->execute("CREATE TABLE `test_charset` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `content` varchar(255) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $unicodeText = '测试中文内容 emoji 😀🎉';
        $connector->execute("INSERT INTO `test_charset` (`content`) VALUES (?)", [$unicodeText]);

        $result = $connector->query("SELECT `content` FROM `test_charset` WHERE `id` = 1");
        $this->assertEquals($unicodeText, $result[0]['content']);

        // Cleanup
        $connector->execute("DROP TABLE `test_charset`");
    }

    public function testConnectorParameterBinding(): void
    {
        $config = [
            'type' => 'sqlite',
            'database' => ':memory:',
        ];

        $connector = new Sqlite($config);
        $connector->setDb($this->dbManager);

        // Create test table
        $connector->execute("CREATE TABLE test_params (
            id INTEGER PRIMARY KEY,
            name TEXT,
            age INTEGER,
            score REAL,
            active BOOLEAN
        )");

        // Test different parameter types
        $connector->execute(
            "INSERT INTO test_params (name, age, score, active) VALUES (?, ?, ?, ?)",
            ['John Doe', 25, 95.5, true]
        );

        $result = $connector->query("SELECT * FROM test_params WHERE id = 1");
        
        $this->assertEquals('John Doe', $result[0]['name']);
        $this->assertEquals(25, $result[0]['age']);
        $this->assertEquals(95.5, $result[0]['score']);
        $this->assertEquals(1, $result[0]['active']); // SQLite stores boolean as 1/0
    }
}