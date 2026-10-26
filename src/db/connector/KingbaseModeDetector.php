<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\db\connector;

use PDO;

/**
 * KingbaseES 兼容模式探测器。
 *
 * 仅在连接配置使用 type=kingbase 时建立一次短连接，读取服务端 database_mode。
 * 探测结果由统一Kingbase连接入口用于创建模式专用连接器，且不替代正式连接的二次校验。
 */
final class KingbaseModeDetector
{
    /**
     * 探测当前 KingbaseES 实例的兼容模式。
     *
     * @param array $config ThinkORM连接配置
     *
     * @throws \PDOException             探测连接失败
     * @throws \UnexpectedValueException PDO驱动或兼容模式不受支持
     *
     * @return string mysql、oracle或sqlserver
     */
    public static function detect(array $config): string
    {
        $probeConfig = self::firstEndpoint($config);
        $dsn = self::buildDsn($probeConfig);
        $params = is_array($probeConfig['params'] ?? null) ? $probeConfig['params'] : [];
        $params = $params + [
            PDO::ATTR_CASE => PDO::CASE_LOWER,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO(
                $dsn,
                (string) ($probeConfig['username'] ?? ''),
                (string) ($probeConfig['password'] ?? ''),
                $params
            );

            $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if ('kdb' !== $driver) {
                throw new \UnexpectedValueException(
                    'KingbaseES automatic mode detection requires PDO_KDB, got PDO_' . $driver
                );
            }

            $mode = self::normalizeMode((string) $pdo
                ->query("SELECT current_setting('database_mode', true)")
                ->fetchColumn());
            $pdo = null;
        } catch (\PDOException $exception) {
            $message = self::sanitizeDiagnostic($exception->getMessage(), (string) ($probeConfig['password'] ?? ''));
            $details = sprintf(
                'host=%s, port=%s, database=%s',
                self::sanitizeDiagnostic((string) ($probeConfig['hostname'] ?? ''), ''),
                self::sanitizeDiagnostic((string) ($probeConfig['hostport'] ?? ''), ''),
                self::sanitizeDiagnostic((string) ($probeConfig['database'] ?? ''), '')
            );
            $code = is_numeric($exception->getCode()) ? (int) $exception->getCode() : 0;

            throw new \PDOException('KES mode detection failed (' . $details . '): ' . $message, $code, $exception);
        }

        if ('' === $mode) {
            throw new \UnexpectedValueException('Unsupported or empty KES database_mode returned by server');
        }

        $configuredMode = self::configuredMode($config);
        if ('' !== $configuredMode && $configuredMode !== $mode) {
            throw new \UnexpectedValueException(
                'KES compatibility mode mismatch: configured ' . $configuredMode . ', server ' . $mode
            );
        }

        return $mode;
    }

    /**
     * 将服务端模式映射为现有ThinkORM连接器类型。
     */
    public static function connectorType(string $mode): string
    {
        return match (self::normalizeMode($mode)) {
            'mysql' => 'kingbaseMysql',
            'oracle' => 'kingbaseOracle',
            'sqlserver' => 'kingbaseSqlsrv',
            default => throw new \InvalidArgumentException('Unsupported KES database_mode: ' . $mode),
        };
    }

    /**
     * 读取可选的模式约束；省略时完全采用自动探测结果。
     */
    private static function configuredMode(array $config): string
    {
        foreach (['kingbase_mode', 'kdb_mode', 'mode'] as $key) {
            if (isset($config[$key]) && '' !== trim((string) $config[$key])) {
                $configured = strtolower(trim((string) $config[$key]));
                if ('auto' === $configured) {
                    return '';
                }
                $mode = self::normalizeMode($configured);
                if ('' === $mode) {
                    throw new \InvalidArgumentException('Invalid configured KES mode: ' . $config[$key]);
                }

                return $mode;
            }
        }

        return '';
    }

    /**
     * 规范化数据库返回值及兼容别名。
     */
    private static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            'mysql', 'kingbasemysql' => 'mysql',
            'oracle', 'kingbaseoracle' => 'oracle',
            'sqlsrv', 'sqlserver', 'kingbasesqlsrv',
            'kingbasesqlserver' => 'sqlserver',
            default => '',
        };
    }

    /**
     * 自动探测只负责选择方言；读写分离的其他端点由正式连接器逐一二次校验。
     */
    private static function firstEndpoint(array $config): array
    {
        foreach (['hostname', 'hostport', 'database', 'username', 'password', 'dsn'] as $key) {
            if (!isset($config[$key])) {
                continue;
            }
            if (is_array($config[$key])) {
                $config[$key] = reset($config[$key]);
            } elseif (is_string($config[$key]) && str_contains($config[$key], ',')) {
                $config[$key] = explode(',', $config[$key], 2)[0];
            }
        }

        return $config;
    }

    /**
     * 生成PDO_KDB DSN；显式DSN仍必须使用kdb协议。
     */
    private static function buildDsn(array $config): string
    {
        $dsn = trim((string) ($config['dsn'] ?? ''));
        if ('' !== $dsn) {
            if (!str_starts_with(strtolower($dsn), 'kdb:')) {
                throw new \InvalidArgumentException('KingbaseES automatic mode detection requires a kdb: DSN');
            }

            return $dsn;
        }

        $hostname = trim((string) ($config['hostname'] ?? ''));
        $database = trim((string) ($config['database'] ?? ''));
        if ('' === $hostname || '' === $database) {
            throw new \InvalidArgumentException('KDB hostname and database are required for automatic mode detection');
        }

        $dsn = 'kdb:host=' . $hostname . ';dbname=' . $database;
        if ('' !== trim((string) ($config['hostport'] ?? ''))) {
            $dsn .= ';port=' . $config['hostport'];
        }

        return $dsn;
    }

    /**
     * 对探测异常进行脱敏并限制为单行。
     */
    private static function sanitizeDiagnostic(string $value, string $password): string
    {
        if ('' !== $password) {
            $value = str_replace($password, '***', $value);
        }
        $value = trim(str_replace(["\r", "\n", "\t"], ' ', $value));

        return strlen($value) > 500 ? substr($value, 0, 497) . '...' : $value;
    }
}
