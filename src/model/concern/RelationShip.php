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

namespace think\model\concern;

use Closure;
use think\Collection;
use think\db\BaseQuery as Query;
use think\db\exception\DbException as Exception;
use think\db\exception\InvalidArgumentException;
use think\helper\Str;
use think\model\contract\Modelable as Model;
use think\model\Relation;
use think\model\relation\BelongsTo;
use think\model\relation\BelongsToMany;
use think\model\relation\HasMany;
use think\model\relation\HasManyThrough;
use think\model\relation\HasOne;
use think\model\relation\HasOneThrough;
use think\model\relation\MorphMany;
use think\model\relation\MorphOne;
use think\model\relation\MorphTo;
use think\model\relation\MorphToMany;
use think\model\relation\OneToOne;

/**
 * 实体模型关联处理.
 */
trait RelationShip
{
    /**
     * 关联数据写入或删除.
     *
     * @param array $relation 关联
     *
     * @return $this
     */
    public function together(array $relation)
    {
        $this->setOption('together', $relation);

        return $this;
    }

    /**
     * 设置关联JOIN数据.
     *
     * @param array $relations 关联数据
     *
     * @return void
     */
    private function parseRelationData(array $relations)
    {
        foreach ($relations as $relation => $val) {
            $relation = $this->getRealFieldName($relation);
            $type     = $this->getFields($relation);
            $bind     = $this->getBindAttr($this->getOption('bindAttr'), $relation);
            if (!empty($bind)) {
                // 绑定关联属性
                $this->bindRelationAttr($val, $bind);
            } elseif (is_subclass_of($type, Entity::class)) {
                // 明确类型直接设置关联属性
                $this->$relation = new $type($val);
            } else {
                // 寄存关联数据
                $this->setTempRelation($relation, $val);
            }
        }
    }

    /**
     * 寄存关联数据.
     *
     * @param string $relation 关联属性
     * @param array  $data  关联数据
     *
     * @return void
     */
    private function setTempRelation(string $relation, array $data)
    {
        $this->setWeakData('relation', $relation, $data);
    }

    /**
     * 获取寄存的关联数据.
     *
     * @param string $relation 关联属性
     *
     * @return array
     */
    public function getRelation(string $relation): array
    {
        return $this->getWeakData('relation', $relation, []);
    }

    /**
     * 写入模型关联数据（一对一）.
     *
     * @param array $relations 数据
     * @param bool  $isUpdate  是否更新
     * @return void
     */
    private function relationSave(array $relations = [], bool $isUpdate = true)
    {
        foreach ($relations as $name => $relation) {
            if ($relation && in_array($name, $this->getOption('together'))) {
                $isUpdate ? $relation->save() : $this->$name()->save($relation);
            }
        }
    }

    /**
     * 删除模型关联数据（一对一）.
     *
     * @param array $relations 数据
     * @return void
     */
    private function relationDelete(array $relations = [])
    {
        foreach ($relations as $name => $relation) {
            if ($relation && in_array($name, $this->getOption('together'))) {
                $relation->delete();
            }
        }
    }

    /**
     * 获取关联的外键名.
     *
     * @param string $relation 关联名
     * @return string|null
     */
    protected function getRelationKey(string $relation)
    {
        $relationKey = $this->getOption('relation_keys', []);
        return $relationKey[$relation] ?? null;
    }

    /**
     * 获取关联数据
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    protected function getRelationData(string $name)
    {
        $method = Str::camel($name);
        if (method_exists($this, $method)) {
            $modelRelation = $this->$method();
            if ($modelRelation instanceof Relation) {
                $value = $modelRelation->getRelation();
                $this->setData($name, $value);
                return $value;
            }
        }
    }

    protected function getBindAttr($bind, $name)
    {
        if (true === $bind || (isset($bind[$name]) && true === $bind[$name])) {
            return true;
        }
        return $bind[$name] ?? [];
    }

    /**
     * 设置关联绑定数据
     *
     * @param Entity|array $entity 关联实体对象
     * @param array|bool  $bind  绑定属性
     * @return void
     */
    public function bindRelationAttr($entity, $bind = [])
    {
        $data = is_array($entity) ? $entity : $entity->getData();
        foreach ($data as $key => $val) {
            if (isset($bind[$key])) {
                $this->set($bind[$key], $val);
            } elseif ((true === $bind || in_array($key, $bind)) && !$this->__isset($key)) {
                $this->set($key, $val);
            }
        }
    }

    /**
     * 设置父模型对象
     *
     * @param self $model 模型对象
     *
     * @return $this
     */
    public function setParent($model)
    {
        $this->setOption('parent', $model);

        return $this;
    }

    /**
     * 获取父模型对象
     *
     * @return self
     */
    public function getParent()
    {
        return $this->getOption('parent');
    }

    /**
     * 设置关联数据.
     *
     * @param string $relation 关联属性
     * @param array  $data  关联数据
     *
     * @return void
     */
    public function setRelation(string $relation, $data)
    {
        $this->__set($relation, $data);
    }

    /**
     * 根据关联条件查询当前模型.
     *
     * @param string $relation 关联方法名
     * @param mixed  $operator 比较操作符
     * @param int    $count    个数
     * @param string $id       关联表的统计字段
     * @param string $joinType JOIN类型
     * @param Query  $query    Query对象
     *
     * @return Query
     */
    public static function has(string $relation, string $operator = '>=', int $count = 1, string $id = '*', string $joinType = '', ?Query $query = null): Query
    {
        return (new static())
            ->$relation()
            ->has($operator, $count, $id, $joinType, $query);
    }

    /**
     * 根据关联条件查询当前模型.
     *
     * @param string $relation 关联方法名
     * @param mixed  $where    查询条件（数组或者闭包）
     * @param mixed  $fields   字段
     * @param string $joinType JOIN类型
     * @param Query  $query    Query对象
     *
     * @return Query
     */
    public static function hasWhere(string $relation, $where = [], string $fields = '*', string $joinType = '', ?Query $query = null): Query
    {
        return (new static())
            ->$relation()
            ->hasWhere($where, $fields, $joinType, $query);
    }
        
    /**
     * 查询当前模型的关联数据.
     *
     * @param array $relations        关联名
     * @param array $withRelationAttr 关联获取器
     *
     * @return void
     */
    public function relationQuery(array $relations, array $withRelationAttr = []): void
    {
        foreach ($relations as $key => $relation) {
            $subRelation = [];
            $closure     = null;

            if ($relation instanceof Closure) {
                // 支持闭包查询过滤关联条件
                $closure  = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $relation;
                $relation    = $key;
            } elseif (str_contains($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);
            }

            $method         = Str::camel($relation);
            $relationName   = Str::snake($relation);
            $relationResult = $this->$method();

            if (isset($withRelationAttr[$relationName])) {
                $relationResult->withAttr($withRelationAttr[$relationName]);
            }

            $this->setRelation($relation, $relationResult->getRelation((array) $subRelation, $closure));
        }
    }

   /**
     * 预载入关联查询 JOIN方式.
     *
     * @param Query   $query    Query对象
     * @param string  $relation 关联方法名
     * @param mixed   $field    字段
     * @param string  $joinType JOIN类型
     * @param Closure $closure  闭包
     * @param bool    $first
     *
     * @return bool
     */
    public function eagerly(Query $query, string $relation, $field, string $joinType = '', ?Closure $closure = null, bool $first = false): bool
    {
        $relation = Str::camel($relation);
        $class    = $this->$relation();

        if ($class instanceof OneToOne) {
            $class->eagerly($query, $relation, $field, $joinType, $closure, $first);

            return true;
        }

        return false;
    }

    /**
     * 预载入关联查询 返回数据集.
     *
     * @param array  $resultSet        数据集
     * @param array  $relations        关联名
     * @param array  $withRelationAttr 关联获取器
     * @param bool   $join             是否为JOIN方式
     * @param mixed  $cache            关联缓存
     *
     * @return void
     */
    public function eagerlyResultSet(array $resultSet, array $relations, array $withRelationAttr = [], bool $join = false, $cache = false): void
    {
        foreach ($relations as $key => $relation) {
            $subRelation = [];
            $closure     = null;

            if ($relation instanceof Closure) {
                $closure  = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $relation;
                $relation    = $key;
            } elseif (str_contains($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);

                $subRelation = [$subRelation];
            }

            $relationName   = $relation;
            $relation       = Str::camel($relation);
            $relationResult = $this->$relation();

            if (isset($withRelationAttr[$relationName])) {
                $relationResult->withAttr($withRelationAttr[$relationName]);
            }

            if (is_scalar($cache)) {
                $relationCache = [$cache];
            } else {
                $relationCache = $cache[$relationName] ?? $cache;
            }

            $relationResult->eagerlyResultSet($resultSet, $relationName, $subRelation, $closure, $relationCache, $join);            
        }
    }

    /**
     * 预载入关联查询 返回模型对象
     *
     * @param array $relations        关联
     * @param array $withRelationAttr 关联获取器
     * @param bool  $join             是否为JOIN方式
     * @param mixed $cache            关联缓存
     *
     * @return void
     */
    public function eagerlyResult(Model $result, array $relations, array $withRelationAttr = [], bool $join = false, $cache = false): void
    {
        foreach ($relations as $key => $relation) {
            $subRelation = [];
            $closure     = null;

            if ($relation instanceof Closure) {
                $closure  = $relation;
                $relation = $key;
            }

            if (is_array($relation)) {
                $subRelation = $relation;
                $relation    = $key;
            } elseif (str_contains($relation, '.')) {
                [$relation, $subRelation] = explode('.', $relation, 2);

                $subRelation = [$subRelation];
            }

            $relationName   = $relation;
            $relation       = Str::camel($relation);
            $relationResult = $this->$relation();

            if (isset($withRelationAttr[$relationName])) {
                $relationResult->withAttr($withRelationAttr[$relationName]);
            }

            if (is_scalar($cache)) {
                $relationCache = [$cache];
            } else {
                $relationCache = $cache[$relationName] ?? [];
            }

            $relationResult->eagerlyResult($result, $relationName, $subRelation, $closure, $relationCache, $join);
        }
    }

    /**
     * 绑定（一对一）关联属性到当前模型.
     *
     * @param string $relation 关联名称
     * @param array  $attrs    绑定属性
     *
     * @throws Exception
     *
     * @return $this
     */
    public function bindAttr(string $relation, array $attrs = [])
    {
        $relation = $this->__get($relation);

        foreach ($attrs as $key => $attr) {
            if (is_numeric($key)) {
                if (!is_string($attr)) {
                    throw new InvalidArgumentException('bind attr must be string:' . $key);
                }

                $key = $attr;
            }

            if (null !== $this->getOrigin($key)) {
                throw new Exception('bind attr has exists:' . $key);
            }

            if ($attr instanceof Closure) {
                $value = $attr($relation, $key, $this);
            } else {
                $value = $relation?->get($attr);
            }

            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * 关联统计
     *
     * @param Query  $query       查询对象
     * @param array  $relations   关联名
     * @param string $aggregate   聚合查询方法
     * @param string $field       字段
     * @param bool   $useSubQuery 子查询
     *
     * @return void
     */
    public function relationCount(Query $query, array $relations, string $aggregate = 'sum', string $field = '*', bool $useSubQuery = true): void
    {
        foreach ($relations as $key => $relation) {
            $closure = $name = null;

            if ($relation instanceof Closure) {
                $closure  = $relation;
                $relation = $key;
            } elseif (is_string($key)) {
                $name     = $relation;
                $relation = $key;
            }

            $relation = Str::camel($relation);

            if ($useSubQuery) {
                $count = $this->$relation()->getRelationCountQuery($closure, $aggregate, $field, $name);
            } else {
                $count = $this->$relation()->relationCount($this, $closure, $aggregate, $field, $name);
            }

            if (empty($name)) {
                $name = Str::snake($relation) . '_' . $aggregate;
            }

            if ($useSubQuery) {
                $query->field(['(' . $count . ')' => $name]);
            } else {
                $this->set($name, $count);
            }
        }
    }

    /**
     * HAS ONE 关联定义.
     *
     * @param string $model      模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey   当前主键
     *
     * @return HasOne
     */
    public function hasOne(string $model, string $foreignKey = '', string $localKey = ''): HasOne
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->getName());

        return new HasOne($this, $model, $foreignKey, $localKey);
    }

    /**
     * BELONGS TO 关联定义.
     *
     * @param string $model      模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey   关联主键
     *
     * @return BelongsTo
     */
    public function belongsTo(string $model, string $foreignKey = '', string $localKey = ''): BelongsTo
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $foreignKey = $foreignKey ?: $this->getForeignKey((new $model())->getName());
        $localKey   = $localKey ?: (new $model())->getPk();
        $trace      = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $relation   = Str::snake($trace[1]['function']);

        return new BelongsTo($this, $model, $foreignKey, $localKey, $relation);
    }

    /**
     * HAS MANY 关联定义.
     *
     * @param string $model      模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey   当前主键
     *
     * @return HasMany
     */
    public function hasMany(string $model, string $foreignKey = '', string $localKey = ''): HasMany
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->getName());

        return new HasMany($this, $model, $foreignKey, $localKey);
    }

    /**
     * HAS MANY 远程关联定义.
     *
     * @param string $model      模型名
     * @param string $through    中间模型名
     * @param string $foreignKey 关联外键
     * @param string $throughKey 关联外键
     * @param string $localKey   当前主键
     * @param string $throughPk  中间表主键
     *
     * @return HasManyThrough
     */
    public function hasManyThrough(string $model, string $through, string $foreignKey = '', string $throughKey = '', string $localKey = '', string $throughPk = ''): HasManyThrough
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $through    = $this->parseRelationModel($through);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->getName());
        $throughKey = $throughKey ?: $this->getForeignKey((new $through())->getName());
        $throughPk  = $throughPk ?: (new $through())->getPk();

        return new HasManyThrough($this, $model, $through, $foreignKey, $throughKey, $localKey, $throughPk);
    }

    /**
     * HAS ONE 远程关联定义.
     *
     * @param string $model      模型名
     * @param string $through    中间模型名
     * @param string $foreignKey 关联外键
     * @param string $throughKey 关联外键
     * @param string $localKey   当前主键
     * @param string $throughPk  中间表主键
     *
     * @return HasOneThrough
     */
    public function hasOneThrough(string $model, string $through, string $foreignKey = '', string $throughKey = '', string $localKey = '', string $throughPk = ''): HasOneThrough
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $through    = $this->parseRelationModel($through);
        $localKey   = $localKey ?: $this->getPk();
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->getName());
        $throughKey = $throughKey ?: $this->getForeignKey((new $through())->getName());
        $throughPk  = $throughPk ?: (new $through())->getPk();

        return new HasOneThrough($this, $model, $through, $foreignKey, $throughKey, $localKey, $throughPk);
    }

    /**
     * BELONGS TO MANY 关联定义.
     *
     * @param string $model      模型名
     * @param string $middle     中间表/模型名
     * @param string $foreignKey 关联外键
     * @param string $localKey   当前模型关联键
     *
     * @return BelongsToMany
     */
    public function belongsToMany(string $model, string $middle = '', string $foreignKey = '', string $localKey = ''): BelongsToMany
    {
        // 记录当前关联信息
        $model      = $this->parseRelationModel($model);
        $name       = Str::snake(class_basename($model));
        $middle     = $middle ?: Str::snake($this->name) . '_' . $name;
        $foreignKey = $foreignKey ?: $name . '_id';
        $localKey   = $localKey ?: $this->getForeignKey($this->getName());

        return new BelongsToMany($this, $model, $middle, $foreignKey, $localKey);
    }

    /**
     * MORPH  One 关联定义.
     *
     * @param string       $model 模型名
     * @param string|array $morph 多态字段信息
     * @param string       $type  多态类型
     *
     * @return MorphOne
     */
    public function morphOne(string $model, string | array | null $morph = null, string $type = ''): MorphOne
    {
        // 记录当前关联信息
        $model = $this->parseRelationModel($model);

        if (is_null($morph)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $morph = Str::snake($trace[1]['function']);
        }

        [$morphType, $foreignKey] = $this->parseMorph($morph);

        $type = $type ?: get_class($this);

        return new MorphOne($this, $model, $foreignKey, $morphType, $type);
    }

    /**
     * MORPH  MANY 关联定义.
     *
     * @param string       $model 模型名
     * @param string|array $morph 多态字段信息
     * @param string       $type  多态类型
     *
     * @return MorphMany
     */
    public function morphMany(string $model, string | array | null $morph = null, string $type = ''): MorphMany
    {
        // 记录当前关联信息
        $model = $this->parseRelationModel($model);

        if (is_null($morph)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $morph = Str::snake($trace[1]['function']);
        }

        $type = $type ?: get_class($this);

        [$morphType, $foreignKey] = $this->parseMorph($morph);

        return new MorphMany($this, $model, $foreignKey, $morphType, $type);
    }

    /**
     * MORPH TO 关联定义.
     *
     * @param string|array $morph 多态字段信息
     * @param array        $alias 多态别名定义
     *
     * @return MorphTo
     */
    public function morphTo(string | array | null $morph = null, array $alias = []): MorphTo
    {
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $relation = Str::snake($trace[1]['function']);

        if (is_null($morph)) {
            $morph = $relation;
        }

        [$morphType, $foreignKey] = $this->parseMorph($morph);

        return new MorphTo($this, $morphType, $foreignKey, $alias, $relation);
    }

    /**
     * MORPH TO MANY关联定义.
     *
     * @param string       $model    模型名
     * @param string       $middle   中间表名/模型名
     * @param string|array $morph    多态字段信息
     * @param string       $localKey 当前模型关联键
     *
     * @return MorphToMany
     */
    public function morphToMany(string $model, string $middle, string | array | null $morph = null, ?string $localKey = null): MorphToMany
    {
        if (is_null($morph)) {
            $morph = $middle;
        }

        [$morphType, $morphKey] = $this->parseMorph($morph);

        $model    = $this->parseRelationModel($model);
        $name     = Str::snake(class_basename($model));
        $localKey = $localKey ?: $this->getForeignKey($name);

        return new MorphToMany($this, $model, $middle, $morphType, $morphKey, $localKey);
    }

    /**
     * MORPH BY MANY关联定义.
     *
     * @param string       $model      模型名
     * @param string       $middle     中间表名/模型名
     * @param string|array $morph      多态字段信息
     * @param string       $foreignKey 关联外键
     *
     * @return MorphToMany
     */
    public function morphByMany(string $model, string $middle, string | array | null $morph = null, ?string $foreignKey = null): MorphToMany
    {
        if (is_null($morph)) {
            $morph = $middle;
        }

        [$morphType, $morphKey] = $this->parseMorph($morph);

        $model      = $this->parseRelationModel($model);
        $foreignKey = $foreignKey ?: $this->getForeignKey($this->getName());

        return new MorphToMany($this, $model, $middle, $morphType, $morphKey, $foreignKey, true);
    }

    /**
     * 解析多态
     *
     * @param string|array $morph
     *
     * @return array
     */
    protected function parseMorph(string | array $morph): array
    {
        if (is_array($morph)) {
            [$morphType, $foreignKey] = $morph;
        } else {
            $morphType  = $morph . '_type';
            $foreignKey = $morph . '_id';
        }

        return [$morphType, $foreignKey];
    }

    /**
     * 解析模型的完整命名空间.
     *
     * @param string $model 模型名（或者完整类名）
     *
     * @return string
     */
    protected function parseRelationModel(string $model): string
    {
        if (!str_contains($model, '\\')) {
            $path = explode('\\', static::class);
            array_pop($path);
            array_push($path, Str::studly($model));
            $model = implode('\\', $path);
        }

        return $model;
    }

    /**
     * 获取模型的默认外键名.
     *
     * @param string $name 模型名
     *
     * @return string
     */
    protected function getForeignKey(string $name): string
    {
        if (str_contains($name, '\\')) {
            $name = class_basename($name);
        }

        return Str::snake($name) . '_id';
    }
}
