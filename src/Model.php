<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think;

use ArrayAccess;
use Closure;
use InvalidArgumentException;
use JsonSerializable;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\db\BaseQuery as Query;
use think\db\Express;
use think\exception\ValidateException;
use think\model\Collection;
use think\model\contract\Modelable;
use think\model\View;
use WeakMap;

/**
 * Class Model.
 * @mixin Query
 *
 * @method static void  onAfterRead(Model $model)     after_read事件定义
 * @method static mixed onBeforeInsert(Model $model)  before_insert事件定义
 * @method static void  onAfterInsert(Model $model)   after_insert事件定义
 * @method static mixed onBeforeUpdate(Model $model)  before_update事件定义
 * @method static void  onAfterUpdate(Model $model)   after_update事件定义
 * @method static mixed onBeforeWrite(Model $model)   before_write事件定义
 * @method static void  onAfterWrite(Model $model)    after_write事件定义
 * @method static mixed onBeforeDelete(Model $model)  before_write事件定义
 * @method static void  onAfterDelete(Model $model)   after_delete事件定义
 * @method static void  onBeforeRestore(Model $model) before_restore事件定义
 * @method static void  onAfterRestore(Model $model)  after_restore事件定义
 */
abstract class Model implements JsonSerializable, ArrayAccess, Arrayable, Jsonable, Modelable
{
    use model\concern\Attribute;
    use model\concern\AutoWriteData;
    use model\concern\Conversion;
    use model\concern\DbConnect;
    use model\concern\ModelEvent;
    use model\concern\RelationShip;

    private static ?WeakMap $weakMap = null;

    /**
     * 服务注入.
     *
     * @var Closure[]
     */
    protected static array $_maker = [];

    /**
     * 设置服务注入.
     */
    public static function maker(Closure $maker): void
    {
        static::$_maker[] = $maker;
    }

    /**
     * 设置容器对象的依赖注入方法.（用于兼容）
     *
     * @param callable $callable 依赖注入方法
     *
     * @return void
     */
    public static function setInvoker(callable $callable): void
    {
    }

    /**
     * 调用反射执行模型方法 支持参数绑定.
     *
     * @param mixed $method
     * @param array $vars   参数
     *
     * @return mixed
     */
    public function invoke($method, array $vars = [])
    {
        if (is_string($method)) {
            $method = [$this, $method];
        }

        $invoker = $this->getOption('invoker');
        if ($invoker) {
            return $invoker($method instanceof Closure ? $method : Closure::fromCallable($method), $vars);
        }

        return call_user_func_array($method, $vars);
    }

    /**
     * 架构函数.
     *
     * @param array|object $data 实体模型数据
     */
    public function __construct(array | object $data = [])
    {
        // 获取实体模型参数
        $options = $this->getOptions();

        if (!self::$weakMap) {
            self::$weakMap = new WeakMap;
        }

        self::$weakMap[$this] = [
            'get'          => [],
            'data'         => [],
            'origin'       => [],
            'relation'     => [],
            'together'     => [],
            'allow'        => [],
            'withAttr'     => [],
            'schema'       => $options['schema'] ?? [],
            'updateTime'   => $options['updateTime'] ?? 'update_time',
            'createTime'   => $options['createTime'] ?? 'create_time',
            'suffix'       => $options['suffix'] ?? '',
            'pk'           => $options['pk'] ?? 'id',
            'validate'     => $options['validate'] ?? $this->parseValidate(),
            'type'         => $options['type'] ?? [],
            'readonly'     => $options['readonly'] ?? [],
            'disuse'       => $options['disuse'] ?? [],
            'hidden'       => $options['hidden'] ?? [],
            'visible'      => $options['visible'] ?? [],
            'append'       => $options['append'] ?? [],
            'mapping'      => $options['mapping'] ?? [],
            'strict'       => $options['strict'] ?? true,
            'bindAttr'     => $options['bindAttr'] ?? [],
            'autoRelation' => $options['autoRelation'] ?? [],
        ];

        // 设置额外参数
        $this->setOptions(array_diff_key($options, self::$weakMap[$this]));

        if (!empty(static::$_maker)) {
            foreach (static::$_maker as $maker) {
                call_user_func($maker, $this);
            }
        }

        // 初始化模型
        $this->init();

        // 设置数据库连接
        $this->initDb();

        // 初始化数据
        $this->initializeData($data);
    }

    /**
     *  初始化模型.
     *
     * @return void
     */
    protected function init()
    {}

    /**
     * 在实体模型中定义 返回相关配置参数.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [];
    }

    /**
     * 批量设置模型参数
     * @param array  $options  值
     * @return void
     */
    public function setOptions(array $options): void
    {
        foreach ($options as $name => $value) {
            $this->setOption($name, $value);
        }
    }

    /**
     * 设置模型参数
     *
     * @param string $name  参数名
     * @param mixed  $value  值
     *
     * @return $this
     */
    public function setOption(string $name, $value)
    {
        self::$weakMap[$this][$name] = $value;
        return $this;
    }

    /**
     * 获取模型参数
     *
     * @param string $name  参数名
     * @param mixed  $default  默认值
     *
     * @return mixed
     */
    public function getOption(string $name, $default = null)
    {
        // 兼容读取3.0版本的属性参数定义
        if (property_exists($this, $name) && isset($this->$name)) {
            return $this->$name;
        }
        return self::$weakMap[$this][$name] ?? $default;
    }

    private function setWeakData(string $key, string $name, $value): void
    {
        self::$weakMap[$this][$key][$name] = $value;
    }

    private function getWeakData(string $key, string $name, $default = null)
    {
        return self::$weakMap[$this][$key][$name] ?? $default;
    }

    /**
     * 创建新的模型实例.
     *
     * @param array $data
     *
     * @return Model|Entity
     */
    public function newInstance(array $data = [])
    {
        $class = $this->getOption('entityClass', str_replace('\\model\\', '\\entity\\', static::class));
        $model = new static($data);
        if (!empty($data)) {
            $model->exists(true);
        }
        if (class_exists($class) && is_subclass_of($class, Entity::class)) {
            $entity = new $class($model);
            $model->entity($entity);
            return $entity;
        }
        return $model;
    }

    public function entity(Entity $entity): void
    {
        $this->setOption('entity', $entity);
    }

    public function getEntity(): ?Entity
    {
        return $this->getOption('entity');
    }

    /**
     * 解析对应验证类.
     *
     * @return string
     */
    protected function parseValidate(): string
    {
        $validate = str_contains(static::class, '\\model\\') ? str_replace('\\model\\', '\\validate\\', static::class) : '';
        return $validate && class_exists($validate) ? $validate : '';
    }

    /**
     * 验证模型数据.
     *
     * @param array $data 数据
     * @param array $allow 需要验证的字段
     *
     * @throws InvalidArgumentException
     * @return void
     */
    protected function validate(array $data, array $allow = []): void
    {
        $validater = $this->getOption('validate');
        if (!empty($validater) && class_exists('think\validate')) {
            try {
                validate($validater)
                    ->only($allow ?: array_keys($data))
                    ->check($data);
            } catch (ValidateException $e) {
                // 验证失败 输出错误信息
                throw new InvalidArgumentException($e->getError());
            }
        }
    }

    /**
     * 保存模型实例数据.
     *
     * @param array|object $data 数据
     * @param mixed $where 更新条件 true为强制新增
     * @return bool
     */
    public function save(array | object $data = [], $where = []): bool
    {
        if (!empty($data)) {
            // 初始化模型数据
            $this->initializeData($data, true);
        }

        if ($this->isVirtual() || $this->isView()) {
            return true;
        }

        if (false === $this->trigger('BeforeWrite')) {
            return false;
        }

        if (true === $where) {
            $isUpdate = false;
            $where    = [];
        } elseif (!empty($where)) {
            $isUpdate = true;
        } else {
            $isUpdate = $this->isExists() ? true : false;
        }

        if (false === $this->trigger($isUpdate ? 'BeforeUpdate' : 'BeforeInsert')) {
            return false;
        }

        [$data, $relations, $allow] = $this->validateAndFilterData($isUpdate);

        if (empty($data)) {
            // 保存关联数据
            if ($isUpdate && $this->getOption('together')) {
                $this->relationSave($relations, $isUpdate);
            }
            return true;
        }

        // 自动写入数据
        $this->autoWriteData($data, $isUpdate, $allow);

        $db     = $this->getDbWhere($where);
        $result = $db->field($allow)->removeOption('data')->save($data, !$isUpdate);
        if (!$isUpdate) {
            $this->exists(true);
            $this->setKey($db->getLastInsID());
        }
        $this->trigger($isUpdate ? 'AfterUpdate' : 'AfterInsert');
        $this->trigger('AfterWrite');

        // 保存关联数据
        if ($this->getOption('together')) {
            $this->relationSave($relations, $isUpdate);
        }

        // 重置原始数据
        $this->refreshOrigin();
        return true;
    }

    /**
     * 验证和过滤数据
     * @param bool $isUpdate 是否更新
     * @return array [$data, $relations, $allow]
     */
    protected function validateAndFilterData(bool $isUpdate): array
    {
        $data     = $this->getData();
        $origin   = $this->getOrigin();
        $allow    = $this->getOption('allow') ?: array_keys($this->getFields());
        $readonly = $this->getOption('readonly');
        $disuse   = $this->getOption('disuse');
        $allow    = array_diff($allow, $disuse, $isUpdate ? $readonly : []);

        // 验证数据
        $this->validate($data, $allow);

        $relations = [];
        foreach ($data as $name => &$val) {
            if ($val instanceof Modelable) {
                $relations[$name] = $val;
                unset($data[$name]);
            } elseif ($val instanceof Collection || !in_array($name, $allow)) {
                unset($data[$name]);
            } elseif ($isUpdate && !$this->isForce() && $this->isNotRequireUpdate($name, $val, $origin)) {
                unset($data[$name]);
            } else {
                $val = $this->setWithAttr($name, $val, $data);
            }
        }

        return [$data, $relations, $allow];
    }

    /**
     * 数据检查.
     * @param array $data 数据
     * @param bool  $isUpdate 是否更新
     * @return void
     */
    protected function checkData(array &$data, bool $isUpdate): void
    {
    }

    protected function getDbWhere($where)
    {
        $db = $this->db();
        // 检查条件
        if (!empty($where)) {
            $db->where($where);
        } else {
            $db->setKey($this->getKey());
        }
        return $db;
    }

    /**
     * 检查字段是否有更新（主键无需更新）.
     *
     * @param string $name 字段
     * @param mixed $val 值
     * @param array $origin 原始数据
     * @return bool
     */
    protected function isNotRequireUpdate(string $name, $val, array $origin): bool
    {
        return (array_key_exists($name, $origin) && $val === $origin[$name]) || $this->getPk() == $name;
    }

    /**
     * 获取更新数据.
     *
     * @return array
     */
    public function getChangedData(): array
    {
        $data   = $this->getData();
        $origin = $this->getOrigin();
        $change = [];
        foreach ($data as $name => $val) {
            if (!array_key_exists($name, $origin) || $val !== $origin[$name]) {
                $change[$name] = $val;
            }
        }
        return $change;
    }

    /**
     * 判断数据是否有更新.
     *
     * @param string $name 字段
     * @return bool
     */
    public function isChange(string $name): bool
    {
        return $this->getData($name) !== $this->getOrigin($name);
    }

    /**
     * 是否为虚拟模型（不能查询）.
     *
     * @return bool
     */
    public function isVirtual(): bool
    {
        return false;
    }

    /**
     * 设置为视图模型（不能写入）.
     *
     * @return bool
     */
    public function asView(bool $isView = true)
    {
        $this->setOption('is_view', $isView);
    }

    /**
     * 是否为视图模型（不能写入 也不会绑定模型）.
     *
     * @return bool
     */
    public function isView(): bool
    {
        return $this->getOption('is_view', false);
    }

    /**
     * 刷新模型数据.
     *
     * @return static
     */
    public function refresh(): static
    {
        if ($this->isExists()) {
            $data = $this->getQuery()->find($this->getKey())->getData();
            $this->data($data);
        }
        return $this;
    }

    /**
     * 保存多个数据到当前数据对象
     *
     * @param iterable $dataSet 数据
     * @param bool     $replace 是否replace
     *
     * @return Collection
     */
    public static function saveAll(iterable $dataSet, bool $replace = true): Collection
    {
        $result = [];
        foreach ($dataSet as $key => $data) {
            $model = new static;
            $model->replace($replace)->save($data);
            $result[$key] = $model;
        }
        return $model->toCollection($result);
    }

    /**
     * 删除模型数据.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->isVirtual() || $this->isView()) {
            $this->exists(false);
            $this->clear();
            return true;
        }

        if ($this->isEmpty() || false === $this->trigger('BeforeDelete')) {
            return false;
        }

        foreach ($this->getData() as $name => $val) {
            if ($val instanceof Model || $val instanceof Collection) {
                $relations[$name] = $val;
            }
        }

        $result = $this->db()->setKey($this->getKey())->delete();

        $this->trigger('AfterDelete');

        if ($result && !empty($relations)) {
            // 删除关联数据
            $this->relationDelete($relations);
        }

        $this->exists(false);
        $this->clear();
        return true;
    }

    /**
     * 写入数据.
     *
     * @param array|object  $data 数据
     * @param array  $allowField  允许字段
     * @param bool   $replace     使用Replace
     * @return static
     */
    public static function create(array | object $data, array $allowField = [], bool $replace = false): static
    {
        $model = new static();

        $model->allowField($allowField)->replace($replace)->save($data, true);

        return $model;
    }

    /**
     * 更新数据.
     *
     * @param array|object  $data 数据
     * @param mixed  $where       更新条件
     * @param array  $allowField  允许字段
     * @return static
     */
    public static function update(array | object $data, $where = [], array $allowField = []): static
    {
        $model = new static();

        $model->allowField($allowField)->exists(true)->save($data, $where);

        return $model;
    }

    /**
     * 删除记录.
     *
     * @param mixed $data  主键列表 支持闭包查询条件
     * @param bool  $force 是否强制删除
     *
     * @return bool
     */
    public static function destroy($data, bool $force = false): bool
    {
        $model = new static();
        if ($model->isVirtual() || $model->isView()) {
            return true;
        }

        $db = $model->db();

        if (is_array($data) && key($data) !== 0) {
            $db->where($data);
            $data = [];
        } elseif ($data instanceof Closure) {
            $data($db);
            $data = [];
        }

        $resultSet = $db->select((array) $data);

        foreach ($resultSet as $result) {
            $result->force($force)->delete();
        }
        return true;
    }

    /**
     * 字段值增长
     *
     * @param string    $field    字段名
     * @param float|int $step     增长值
     * @param int       $lazyTime 延迟时间（秒）
     *
     * @return $this
     */
    public function inc(string $field, float|int $step = 1, int $lazyTime = 0)
    {
        return $this->set($field, new Express('+', $step, $lazyTime));
    }

    /**
     * 字段值减少.
     *
     * @param string    $field    字段名
     * @param float|int $step     增长值
     * @param int       $lazyTime 延迟时间（秒）
     *
     * @return $this
     */
    public function dec(string $field, float|int $step = 1, int $lazyTime = 0)
    {
        return $this->set($field, new Express('-', $step, $lazyTime));
    }

    /**
     * 查询缓存 数据为空不缓存.
     *
     * @param mixed         $key    缓存key
     * @param int|\DateTime $expire 缓存有效期
     * @param string|array  $tag    缓存标签
     *
     * @return $this
     */
    public function setCache($key = true, $expire = null, $tag = null)
    {
        $this->db()->cache($key, $expire, $tag);
        return $this;
    }

    /**
     * 允许写入字段.
     *
     * @param array $allow 允许字段
     *
     * @return $this
     */
    public function allowField(array $allow)
    {
        $this->setOption('allow', $allow);

        return $this;
    }

    /**
     * 动态设置只读字段.
     *
     * @param array $fields 只读字段
     *
     * @return $this
     */
    public function readonly(array $fields)
    {
        $this->setOption('readonly', $fields);

        return $this;
    }

    /**
     * 强制写入或删除
     *
     * @param bool $force 强制更新
     *
     * @return $this
     */
    public function force(bool $force = true)
    {
        $this->setOption('force', $force);

        return $this;
    }

    /**
     * 判断数据是否强制写入或删除.
     *
     * @return bool
     */
    public function isForce(): bool
    {
        return $this->getOption('force', false);
    }

    /**
     * 获取属性 支持获取器
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * 设置数据 支持类型自动转换
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if ($value instanceof Modelable && $bind = $this->getBindAttr($this->getOption('bindAttr'), $name)) {
            // 关联属性绑定
            $this->bindRelationAttr($value, $bind);
        } else {
            $this->set($name, $value);
        }
    }

    /**
     * 检测数据对象的值
     *
     * @param string $name 名称
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if ($this->isView()) {
            return isset(self::$weakMap[$this]['data'][$name]);
        }
        return !is_null($this->get($name, false));
    }

    /**
     * 销毁数据对象的值
     *
     * @param string $name 名称
     *
     * @return void
     */
    public function __unset(string $name): void
    {
        $name = $this->getRealFieldName($name);

        $this->setWeakData('data', $name, null);
    }

    public function __toString()
    {
        return $this->toJson();
    }

    public function __debugInfo()
    {
        return [
            'data'   => $this->getOption('data'),
            'origin' => $this->getOption('origin'),
            'schema' => $this->getOption('schema'),
        ];
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ArrayAccess
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    public function offsetGet(mixed $name): mixed
    {
        return $this->get($name);
    }

    public function offsetExists(mixed $name): bool
    {
        return $this->__isset($name);
    }

    public function offsetUnset(mixed $name): void
    {
        $this->__unset($name);
    }
}
