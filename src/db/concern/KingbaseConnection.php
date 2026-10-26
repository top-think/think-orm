<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\db\concern;

use PDO;
use think\db\BaseQuery;
use think\db\Raw;

/**
 * KingbaseES 三种兼容模式连接器共用的 PDO_KDB 初始化逻辑。
 */
trait KingbaseConnection
{
    /**
     * Initialized PDO object hashes.
     *
     * @var array<string, bool>
     */
    protected array $kingbaseInitializedLinks = [];

    /**
     * Schema discovered from the current PDO_KDB session.
     */
    protected string $kingbaseResolvedSchema = '';

    /** 当前 INSERT 是否真实具备数据库生成主键的语义。 */
    protected bool $kingbaseGeneratedKeyExpected = false;

    /** 当前 INSERT 已读取到的生成主键，供 Model 后续再次读取。 */
    protected mixed $kingbaseGeneratedKey = null;

    /** 当前 INSERT 的生成主键是否已成功读取。 */
    protected bool $kingbaseGeneratedKeyCached = false;

    /** 当前 Oracle INSERT 实际使用或由字段默认值解析出的序列。 */
    protected ?string $kingbaseInsertSequence = null;

    /**
     * 建立 PDO_KDB 连接并初始化 KingbaseES 会话。
     *
     * 在父连接器完成物理连接后校验驱动、数据库兼容模式和 Schema；连接失败时补充
     * 主机、端口及数据库名等诊断信息，同时对口令脱敏并保留原始 PDOException。
     *
     * @param array      $config         本次连接覆盖配置，为空时使用连接器默认配置
     * @param int        $linkNum        连接编号，用于读写分离或多连接场景
     * @param array|bool $autoConnection 自动连接配置或是否启用自动连接
     *
     * @throws \PDOException             物理连接失败
     * @throws \UnexpectedValueException 驱动、兼容模式或 Schema 与配置不一致
     * @throws \InvalidArgumentException Schema 标识符不合法
     *
     * @return PDO 已完成 KingbaseES 会话初始化的 PDO 对象
     */
    public function connect(array $config = [], $linkNum = 0, $autoConnection = false): PDO
    {
        $mergedConfig = empty($config) ? $this->config : array_merge($this->config, $config);

        try {
            $pdo = parent::connect($config, $linkNum, $autoConnection);
            $this->initializeKingbaseSession($pdo, $mergedConfig);

            return $pdo;
        } catch (\PDOException $e) {
            $message = $this->formatKingbaseConnectionFailure($mergedConfig, $e);
            $code = is_numeric($e->getCode()) ? (int) $e->getCode() : 0;

            throw new \PDOException($message, $code, $e);
        }
    }

    /**
     * 关闭全部数据库连接并清理 KingbaseES 会话派生状态。
     *
     * @return $this 当前连接器实例
     */
    public function close()
    {
        $this->kingbaseInitializedLinks = [];
        $this->kingbaseResolvedSchema = '';
        $this->resetKingbaseGeneratedKeyContext();

        return parent::close();
    }

    /**
     * 执行原生 SQL，并在原生写入前清理 ORM 生成主键上下文。
     *
     * 原生 INSERT 无法从 Query 元数据确认主键生成语义，因此先清除上一次 ORM
     * INSERT 的生成键上下文，避免随后 getLastInsID() 返回历史值。DDL 后的字段
     * 缓存行为继续沿用原 PDOConnection，不在适配层增加自动失效处理。
     *
     * @param string $sql  SQL 语句
     * @param array  $bind 参数绑定
     *
     * @return int 影响行数
     */
    public function execute(string $sql, array $bind = []): int
    {
        if (1 === preg_match('/^\s*(?:INSERT|REPLACE|MERGE)\b/i', $sql)) {
            $this->resetKingbaseGeneratedKeyContext();
        }

        return parent::execute($sql, $bind);
    }

    /**
     * 执行单行 INSERT，并建立仅属于本次 INSERT 的生成主键上下文。
     *
     * 父连接器会在 INSERT 成功后读取生成主键，Model 随后还可能再次调用
     * getLastInsID()。这里缓存本次真实生成的值，同时在显式普通主键场景返回空值，
     * 避免 LAST_INSERT_ID()/CURRVAL/@@IDENTITY 的历史会话值被误认为本次主键。
     *
     * @param BaseQuery $query        查询对象
     * @param bool      $getLastInsID 是否返回生成主键
     *
     * @return mixed 插入结果或本次生成主键
     */
    public function insert(BaseQuery $query, bool $getLastInsID = false)
    {
        $this->prepareKingbaseGeneratedKeyContext($query);

        try {
            $result = parent::insert($query, $getLastInsID);
        } catch (\Throwable $exception) {
            $this->resetKingbaseGeneratedKeyContext();
            throw $exception;
        }

        if ($getLastInsID) {
            return $this->kingbaseGeneratedKeyCached ? $this->kingbaseGeneratedKey : '';
        }

        return $result;
    }

    /**
     * 批量插入不会产生可安全归属到单行的生成主键，执行前清理历史上下文。
     *
     * @param BaseQuery $query   查询对象
     * @param array     $dataSet 数据集
     */
    public function insertAll(BaseQuery $query, array $dataSet = []): int
    {
        $this->resetKingbaseGeneratedKeyContext();

        return parent::insertAll($query, $dataSet);
    }

    /**
     * 固定字段批量插入不会返回单行生成主键，执行前清理历史上下文。
     *
     * @param BaseQuery $query  查询对象
     * @param array     $keys   字段集合
     * @param array     $values 数据集合
     */
    public function insertAllByKeys(BaseQuery $query, array $keys, array $values): int
    {
        $this->resetKingbaseGeneratedKeyContext();

        return parent::insertAllByKeys($query, $keys, $values);
    }

    /**
     * 获取当前连接器要求的 KingbaseES 兼容模式。
     *
     * @return string 兼容模式标识：mysql、oracle 或 sqlserver
     */
    public function getKingbaseMode(): string
    {
        return $this->getExpectedKingbaseMode();
    }

    /**
     * 获取配置或由当前会话解析得到的 Schema。
     *
     * 尚未建立连接时会主动初始化连接；仍无法确定 Schema 时抛出异常，避免后续
     * 元数据查询误落入其他模式。
     *
     * @throws \UnexpectedValueException 无法解析当前 Schema
     *
     * @return string 当前有效的 Schema 名称
     */
    public function getKingbaseSchema(): string
    {
        if ('' === $this->kingbaseResolvedSchema) {
            $this->initConnect(true);
        }

        if ('' === $this->kingbaseResolvedSchema) {
            throw new \UnexpectedValueException('Unable to resolve the current KES schema');
        }

        return $this->kingbaseResolvedSchema;
    }

    /**
     * 返回具体连接器实现的 KingbaseES 兼容模式。
     *
     * @return string 兼容模式标识
     */
    abstract protected function getExpectedKingbaseMode(): string;

    /**
     * 将 KingbaseES 特殊字段映射为正确的 PDO 参数绑定类型。
     *
     * 数组、范围、INTERVAL 和几何类型使用文本形式传输，避免其类型名中的 int
     * 被通用规则误判为整数；blob、binary、bytea、raw 和 image 类型标记为
     * kingbase_lob，以便包含 NUL 字节的数据通过 PDO::PARAM_LOB 安全写入。
     * 其他类型沿用父连接器映射。
     *
     * @param string $type 数据库返回的原始字段类型
     *
     * @return string ThinkORM 内部字段类型
     */
    protected function getFieldType(string $type): string
    {
        $normalizedType = strtolower(trim($type));

        // point、int4range、interval 等复杂类型名包含 int。必须先按文本类型识别，
        // 否则通用规则会使用 PDO::PARAM_INT，将 “{1,2,3}” 或 “[1,10)” 静默转换为 0。
        if (1 === preg_match('/(?:\[\]\s*$|^_)/', $normalizedType)
            || 1 === preg_match('/(?:^|\b)(?:int4range|int8range|numrange|tsrange|tstzrange|daterange|range)(?:\b|$)/', $normalizedType)
            || 1 === preg_match('/^(?:interval|point|line|lseg|box|path|polygon|circle)(?:\b|\s|\()/i', $normalizedType)) {
            return 'string';
        }

        // KES reports MySQL unsigned integers as uint2/uint4/uint8.  Always
        // bind them as strings so uint8 values above PHP_INT_MAX are not
        // truncated before PDO_KDB sends them to the server.
        if (1 === preg_match('/^uint\d+$/i', $normalizedType)) {
            return 'bigint';
        }

        if (1 === preg_match('/^bit(?:\s+varying)?\s*\((\d+)\)/i', trim($type), $match)
            && (int) $match[1] > 1) {
            return 'kingbase_bit_string';
        }

        if (1 === preg_match('/blob|binary|bytea|\braw\b|\bimage\b/i', $type)) {
            return 'kingbase_lob';
        }

        return parent::getFieldType($type);
    }

    /**
     * 根据内部字段类型返回 PDO 参数绑定类型。
     *
     * @param string $type ThinkORM 内部字段类型
     *
     * @return int PDO 参数类型常量
     */
    public function getFieldBindType(string $type): int
    {
        return match ($type) {
            'kingbase_lob' => PDO::PARAM_LOB,
            'kingbase_bit_string' => PDO::PARAM_STR,
            default => parent::getFieldBindType($type),
        };
    }

    /**
     * 只在当前 INSERT 已确认会生成数据库主键时执行回读，并缓存有效结果。
     *
     * @param callable $resolver 实际生成主键查询
     * @param mixed    $empty    不应读取或无法取得时的返回值
     *
     * @return mixed 当前 INSERT 的生成主键或空值
     */
    protected function resolveKingbaseGeneratedKey(callable $resolver, mixed $empty = ''): mixed
    {
        if (!$this->kingbaseGeneratedKeyExpected) {
            return $empty;
        }

        if ($this->kingbaseGeneratedKeyCached) {
            return $this->kingbaseGeneratedKey;
        }

        $value = $resolver();
        if (null === $value || false === $value || '' === $value) {
            return $empty;
        }

        $this->kingbaseGeneratedKey = $value;
        $this->kingbaseGeneratedKeyCached = true;

        return $value;
    }

    /**
     * 返回当前 Oracle INSERT 经验证实际使用的序列。
     */
    protected function getKingbaseInsertSequence(): ?string
    {
        return $this->kingbaseInsertSequence;
    }

    /**
     * 初始化并校验一个物理 PDO_KDB 连接。
     *
     * 每个 PDO 对象仅初始化一次；依次确认驱动名、服务器兼容模式，设置 search_path，
     * 并验证同一连接器创建的不同物理连接使用相同 Schema。
     *
     * @param PDO   $pdo    待初始化的物理连接
     * @param array $config 合并后的数据库连接配置
     *
     * @throws \UnexpectedValueException 驱动、兼容模式或实际 Schema 不符合预期
     * @throws \InvalidArgumentException Schema 标识符不合法
     *
     * @return void
     */
    protected function initializeKingbaseSession(PDO $pdo, array $config): void
    {
        $hash = spl_object_hash($pdo);
        if (isset($this->kingbaseInitializedLinks[$hash])) {
            return;
        }

        $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ('kdb' !== $driver) {
            throw new \UnexpectedValueException('KingbaseES connector requires PDO_KDB, got PDO_' . $driver);
        }

        $serverMode = strtolower((string) $pdo
            ->query("SELECT current_setting('database_mode', true)")
            ->fetchColumn());
        $expectedMode = $this->getExpectedKingbaseMode();
        if ($expectedMode !== $serverMode) {
            throw new \UnexpectedValueException(
                'KES compatibility mode mismatch: configured ' . $expectedMode . ', server ' . $serverMode
            );
        }

        $configuredSchema = trim((string) ($config['schema'] ?? ''));
        if ('' !== $configuredSchema) {
            $configuredSchema = $this->validateKingbaseIdentifier($configuredSchema, 'schema');
            $quotedSchema = '"' . str_replace('"', '""', $configuredSchema) . '"';
            $searchPath = 'public' === strtolower($configuredSchema)
                ? $quotedSchema
                : $quotedSchema . ', public';

            $pdo->exec('SET search_path TO ' . $searchPath);
        }

        $currentSchema = $this->validateKingbaseIdentifier(
            (string) $pdo->query('SELECT current_schema()')->fetchColumn(),
            'current schema'
        );

        if ('' !== $configuredSchema && 0 !== strcasecmp($configuredSchema, $currentSchema)) {
            throw new \UnexpectedValueException(
                'KES schema initialization failed: expected ' . $configuredSchema . ', got ' . $currentSchema
            );
        }

        if ('' !== $this->kingbaseResolvedSchema
            && 0 !== strcasecmp($this->kingbaseResolvedSchema, $currentSchema)) {
            throw new \UnexpectedValueException(
                'KES schema mismatch between connections: expected '
                . $this->kingbaseResolvedSchema . ', got ' . $currentSchema
            );
        }

        $this->kingbaseResolvedSchema = $currentSchema;
        $this->kingbaseInitializedLinks[$hash] = true;
    }

    /**
     * 为当前 INSERT 解析自增、Identity 或 Oracle Sequence 生成语义。
     */
    protected function prepareKingbaseGeneratedKeyContext(BaseQuery $query): void
    {
        $this->resetKingbaseGeneratedKeyContext();

        $options = $query->getOptions();
        $data = is_array($options['data'] ?? null) ? $options['data'] : [];
        $sequence = trim((string) ($options['sequence'] ?? ''));
        $mode = $this->getExpectedKingbaseMode();

        if ('oracle' === $mode && '' !== $sequence && $this->dataUsesKingbaseSequence($data, $sequence)) {
            $this->kingbaseGeneratedKeyExpected = true;
            $this->kingbaseInsertSequence = $sequence;
            return;
        }

        $autoInc = $query->getAutoInc();
        if (!is_string($autoInc) || '' === $autoInc) {
            return;
        }

        [$fieldExists, $fieldValue] = $this->findKingbaseDataField($data, $autoInc);
        if ($fieldExists && !$this->isKingbaseGeneratedFieldValue($fieldValue)) {
            return;
        }

        if ('oracle' !== $mode) {
            $this->kingbaseGeneratedKeyExpected = true;
            return;
        }

        $fields = $this->getFields((string) $query->getTable());
        $metadataSequence = trim((string) ($fields[$autoInc]['sequence'] ?? ''));
        if ('' === $metadataSequence) {
            return;
        }

        if ('' !== $sequence
            && $this->normalizeKingbaseSequence($sequence) !== $this->normalizeKingbaseSequence($metadataSequence)) {
            return;
        }

        $this->kingbaseGeneratedKeyExpected = true;
        $this->kingbaseInsertSequence = $metadataSequence;
    }

    /**
     * 清除生成主键上下文；任何新 INSERT 都必须重新证明生成语义。
     */
    protected function resetKingbaseGeneratedKeyContext(): void
    {
        $this->kingbaseGeneratedKeyExpected = false;
        $this->kingbaseGeneratedKey = null;
        $this->kingbaseGeneratedKeyCached = false;
        $this->kingbaseInsertSequence = null;
    }

    /**
     * 在大小写不敏感的字段数组中读取指定字段。
     *
     * @return array{0:bool,1:mixed}
     */
    private function findKingbaseDataField(array $data, string $field): array
    {
        foreach ($data as $key => $value) {
            if (0 === strcasecmp((string) $key, $field)) {
                return [true, $value];
            }
        }

        return [false, null];
    }

    /**
     * NULL 或 DEFAULT 表达式仍可触发自增/Identity 默认值。
     */
    private function isKingbaseGeneratedFieldValue(mixed $value): bool
    {
        if (null === $value) {
            return true;
        }

        return $value instanceof Raw && 'DEFAULT' === strtoupper(trim($value->getValue()));
    }

    /**
     * 判断 INSERT 数据中是否确实使用指定 Oracle 序列的 NEXTVAL。
     */
    private function dataUsesKingbaseSequence(array $data, string $sequence): bool
    {
        $expected = $this->normalizeKingbaseSequence($sequence) . '.NEXTVAL';

        foreach ($data as $value) {
            if (!$value instanceof Raw) {
                continue;
            }
            $expression = strtoupper((string) preg_replace('/[\s`"\[\]]+/', '', $value->getValue()));
            if (str_contains($expression, $expected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 规范化序列限定名，供显式配置与元数据结果比较。
     */
    private function normalizeKingbaseSequence(string $sequence): string
    {
        return strtoupper((string) preg_replace('/[\s`"\[\]]+/', '', trim($sequence)));
    }

    /**
     * 校验不带限定符的 KingbaseES 数据库对象标识符。
     *
     * 只允许字母或下划线开头，后续可包含字母、数字、下划线、美元符号和井号，
     * 防止配置值直接拼入 SET search_path 等 SQL 时产生歧义或注入风险。
     *
     * @param string $value 待校验的标识符
     * @param string $label 错误信息中使用的对象说明
     *
     * @throws \InvalidArgumentException 标识符格式不合法
     *
     * @return string 去除首尾空白后的合法标识符
     */
    protected function validateKingbaseIdentifier(string $value, string $label): string
    {
        $value = trim($value);
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_$#]*$/', $value)) {
            throw new \InvalidArgumentException('Invalid KES ' . $label . ': ' . $value);
        }

        return $value;
    }

    /**
     * 生成不泄露凭据的 KingbaseES 连接失败信息。
     *
     * @param array         $config    当前连接配置
     * @param \PDOException $exception 原始 PDO 连接异常
     *
     * @return string 单行且长度受限的诊断信息
     */
    protected function formatKingbaseConnectionFailure(array $config, \PDOException $exception): string
    {
        $details = [];
        foreach (['hostname' => 'host', 'hostport' => 'port', 'database' => 'database'] as $key => $label) {
            if (isset($config[$key]) && '' !== (string) $config[$key]) {
                $details[] = $label . '=' . $this->sanitizeKingbaseDiagnostic((string) $config[$key]);
            }
        }

        $message = $exception->getMessage();
        $password = (string) ($config['password'] ?? '');
        if ('' !== $password) {
            $message = str_replace($password, '***', $message);
        }

        return 'KES connection failed (' . ($details ? implode(', ', $details) : 'parameters unavailable') . '): '
            . $this->sanitizeKingbaseDiagnostic($message);
    }

    /**
     * 清理并限制连接诊断文本。
     *
     * 将换行和制表符替换为空格，并将文本限制在 500 字节内，便于日志和页面安全展示。
     *
     * @param string $value 原始诊断文本
     *
     * @return string 清理后的单行诊断文本
     */
    protected function sanitizeKingbaseDiagnostic(string $value): string
    {
        $value = trim(str_replace(["\r", "\n", "\t"], ' ', $value));

        return strlen($value) > 500 ? substr($value, 0, 497) . '...' : $value;
    }
}
