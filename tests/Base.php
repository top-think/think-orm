<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Runner\Version;
use think\db\BaseQuery;
use think\db\ConnectionInterface;
use think\db\connector\Pgsql;
use think\facade\Db;
use function version_compare;

class Base extends TestCase
{
    protected ConnectionInterface $db;
    protected string $dbName;

    public function setUp(): void
    {
        $this->db = Db::connect($this->dbName);

        if ($this->dbName === 'pgsql') {
            pg_reset_function();
            pg_install_func();
        }
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
