<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version;
use think\db\BaseQuery;
use think\db\ConnectionInterface;
use think\db\connector\Pgsql;
use think\facade\Db;
use think\Model;
use function version_compare;

/**
 * @property-read string $dbName;
 */
class Base extends TestCase
{
    protected ConnectionInterface $db;
    protected static string $dbName;

    public function __get(string $name)
    {
        if ($name === 'dbName') {
            return static::$dbName;
        }

        throw new \Exception('Undefined property: ' . static::class . '::$' . $name);
    }

    public function setUp(): void
    {
        $this->db = Db::connect($this->dbName);

        if ($this->dbName === 'pgsql') {
            pg_reset_function();
            pg_install_func();
        }

        // var_dump(static::class . '-' . __FUNCTION__ . '-' . spl_object_id($this));
    }

    protected static function compatibleInsertAll(BaseQuery $query, array $data): void
    {
        if ($query->getConnection() instanceof Pgsql) {
            foreach ($data as $datum) {
                (clone $query)->insert($datum);
            }
        } else {
            $query->insertAll($data);
        }
    }

    protected function proxyAssertMatchesRegularExpression(string $pattern, string $string, string $message = '')
    {
        if (version_compare(Version::id(), '9.1', '>=')) {
            $this->assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            $this->assertRegExp($pattern, $string, $message);
        }
    }
}
