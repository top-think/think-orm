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
 * @property string $connectName;
 */
class TestCaseBase extends TestCase
{
    protected ConnectionInterface $db;
    protected static string $connectName;
    protected static bool $isResetPgScript = false;

    protected static function initModelSupport(): void
    {
        // todo 需要一个重置能力更安全
        Model::maker(function (Model $model) {
            $model->setConnection(static::$connectName);
            var_dump('maker:' . __FUNCTION__ . '-' . $model::class . '-' . spl_object_id($model));
        });
    }

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

        if (static::$connectName === 'pgsql') {
            if (self::$isResetPgScript === false) {
                pg_reset_function();
                self::$isResetPgScript = true;
            }
            pg_install_func();
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

    protected static function compatibleModelInsertAll(Model $query, array $data): void
    {
        if ($query->getConnection() === 'pgsql') {
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
