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

/**
 * @property string $connectName;
 */
class TestCaseBase extends TestCase
{
    protected ConnectionInterface $db;
    protected static string $connectName;
    protected bool $isPgScriptInstalled = false;

    public function __get(string $name)
    {
        if ($name === 'connectName') {
            return static::$connectName;
        }

        throw new \Exception('Undefined property: ' . static::class . '::$' . $name);
    }

    public function setUp(): void
    {
        $this->db ??= Db::connect(static::$connectName);

        if (static::$connectName === 'pgsql' && $this->isPgScriptInstalled) {
            pg_reset_function();
            pg_install_func();
            $this->isPgScriptInstalled = true;
        }

        // var_dump(static::class . '-' . __FUNCTION__ . '-' . spl_object_id($this));
    }

    protected static function compatibleInsertAll(BaseQuery $query, array $data): void
    {
        if ($query->getConnection() instanceof Pgsql) {
            // 当前驱动批量插入不兼容，会产生类型错误，修复后可以移除兼容性
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
