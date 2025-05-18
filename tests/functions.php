<?php

namespace tests;

use function array_column;
use function array_combine;
use function array_map;
use function call_user_func;
use function is_callable;
use function is_int;
use function sort;
use think\db\ConnectionInterface;
use think\facade\Db;

function array_column_ex(array $arr, array $column, ?string $key = null): array
{
    $result = array_map(function ($val) use ($column) {
        $item = [];
        foreach ($column as $index => $key) {
            if (is_callable($key)) {
                $item[$index] = call_user_func($key, $val);
            } elseif (is_int($index)) {
                $item[$key] = $val[$key];
            } else {
                $item[$key] = $val[$index];
            }
        }

        return $item;
    }, $arr);

    if (!empty($key)) {
        $result = array_combine(array_column($arr, 'id'), $result);
    }

    return $result;
}

function array_value_sort(array $arr)
{
    foreach ($arr as &$value) {
        sort($value);
    }
}

function query_mysql_connection_id(ConnectionInterface $connect): int
{
    $cid = $connect->query('SELECT CONNECTION_ID() as cid')[0]['cid'];

    return (int) $cid;
}

function mysql_kill_connection(string $name, $cid)
{
    Db::connect($name)->execute("KILL {$cid}");
}

global $pg_func_installed;
$pg_func_installed = [];

function pg_server_version(string $name = 'pgsql'): string
{
    $pdo = Db::connect($name)->connect();
    $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

    return explode(' ', $version)[0];
}

function pg_install_func(string $name = 'pgsql'): void
{
    global $pg_func_installed;

    if ($pg_func_installed[$name] ?? false) {
        return;
    }

    /** @var \PDO $pdo */
    $pdo = Db::connect($name)->connect();

    $file_path = version_compare(pg_server_version($name), '12.0', '>=')
        ? __DIR__ . '/../src/db/connector/pgsql12.sql'
        : __DIR__ . '/../src/db/connector/pgsql.sql';

    $content = file_get_contents($file_path);
    $statements = preg_split('/;\s*(?=CREATE|COMMENT|DROP)/i', $content);

    $pdo->beginTransaction();
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        $pdo->commit();
    } catch (\Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $pg_func_installed[$name] = true;
}

function pg_reset_function(string $name = 'pgsql'): void
{
    global $pg_func_installed;

    /** @var \PDO $pdo */
    $pdo = Db::connect($name)->connect();

    $statements = [
        "DROP FUNCTION IF EXISTS public.table_msg(a_table_name varchar)",
        "DROP FUNCTION IF EXISTS public.table_msg(a_schema_name varchar, a_table_name varchar)",
        "DROP FUNCTION IF EXISTS pgsql_type(a_type varchar)",
        "DROP TYPE IF EXISTS public.tablestruct CASCADE",
    ];
    $pdo->beginTransaction();
    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $pdo->commit();
    } catch (\Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    $pg_func_installed[$name] = false;
}
