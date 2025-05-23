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

namespace think\model;

use ReflectionClass;
use ReflectionProperty;
use think\Entity;
use think\exception\ValidateException;
use think\helper\Str;
use think\Model;
use think\model\Collection;
use think\model\contract\Modelable;

/**
 * 视图模型
 */
abstract class View extends Entity
{
    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     */
    public function __construct(?Model $model = null)
    {
        parent::__construct($model);

        // 初始化模型数据
        $this->initData();
    }

    /**
     * 初始化实体数据属性.
     *
     * @return void
     */
    protected function initData()
    {
        if ($this->isEmpty()) {
            return ;
        }
        // 获取属性映射关系
        $properties = $this->getEntityPropertiesMap();
        $data       = $this->model()->getData();
        foreach ($properties as $key => $field) {
            if (is_int($key)) {
                // 主模型同名属性
                $this->$field = $this->fetchViewAttr($field, $data);
            } elseif (strpos($field, '->')) {
                // 关联属性或JSON字段映射
                $this->$key = $this->getRelationMapAttr($field, $data);
            } else {
                // 主模型属性映射
                $this->$key = $this->fetchViewAttr($field, $data);
            }
        }
        // 标记数据存在
        $this->exists(true);
    }

    /**
     * 获取关联或JSON字段映射的属性值.
     *
     * @param string $field 视图属性
     * @param array  $data  模型数据
     *
     * @return mixed
     */
    private function getRelationMapAttr(string $field, array $data)
    {
        $items    = explode('->', $field);
        $value    = null;
        $relation = array_shift($items);
        if (isset($data[$relation])) {
            $value = $this->model()->$relation;
            foreach ($items as $item) {
                if (is_array($value)) {
                    $value = $value[$item] ?? null;
                } elseif (is_object($value)) {
                    $value = $value->$item ?? null;
                }
            }
        }
        return $value;
    }

    /**
     * 获取视图属性值（支持视图获取器）.
     *
     * @param string $field 视图属性
     * @param array  $data  模型数据
     *
     * @return mixed
     */
    private function fetchViewAttr(string $field, array $data)
    {
        $method = 'get' . Str::camel($field) . 'Attr';
        $model  = $this->model();
        if (method_exists($this, $method)) {
            // 视图获取器
            $value = $this->$method($model); 
        } elseif ($model->hasData($field)) {
            // 获取主模型数据（支持获取器）
            $value = $model->$field;
        } else {
            // 获取自动映射的属性数据
            $value = $this->getAutoRelationValue($field, $data);
        }
        return $value;
    }

    /**
     * 获取autoMapping自动映射的视图属性值.
     *
     * @param string $field 视图属性
     * @param array  $data  模型数据
     *
     * @return mixed
     */
    private function getAutoRelationValue(string $field, array $data)
    {
        $mapping   = $this->getOption('viewMapping', []);
        $relations = $this->getOption('autoMapping', []);
        foreach ($relations as $relation) {
            if (isset($data[$relation]) && $this->model()->$relation->hasData($field)) {
                $value = $this->model()->$relation->$field;
                if (!isset($mapping[$field])) {
                    $mapping[$field] = $relation . '->' . $field;
                    $this->setOption('viewMapping', $mapping);
                }
                break;
            }
        }
        return $value ?? null;
    }

    /**
     * 获取实体属性列表.
     *
     * @return array
     */
    private function getEntityProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[] = $property->getName();
        }
        return $properties;
    }

    /**
     * 获取包含映射关系的实体属性列表.
     *
     * @return array
     */
    private function getEntityPropertiesMap(): array
    {
        $properties = $this->getOption('view_properties');
        if (empty($properties)) {
            // 获取实体属性列表
            $fields     = $this->getEntityProperties();
            // 获取属性映射列表
            $mapping    = $this->getOption('viewMapping', []);
            $properties = [];
            foreach ($fields as $field) {
                if (isset($mapping[$field])) {
                    // 映射属性
                    $properties[$field] = $mapping[$field];
                } else {
                    // 主模型同名属性
                    $properties[] = $field;
                }
            }
            $this->setOption('view_properties', $properties);
        }

        return $properties;
    }

    /**
     * 解析autoMapping的字段映射
     *
     * @return array
     */
    protected function parseAutoMapping(): array
    {
        $fields     = $this->getEntityProperties();
        $mapping    = $this->getOption('viewMapping', []);
        $relations  = $this->getOption('autoMapping', []);
        if ($relations) {
            array_unshift($relations, $this->model());
            foreach ($fields as $field) {
                if (!isset($mapping[$field])) {
                    foreach ($relations as $relation) {
                        if (is_object($relation)) {
                            if ($relation->getFieldType($field)) {
                                break;
                            }
                        } elseif ($this->model()->$relation()->getFieldType($field)) {
                            $mapping[$field] = $relation . '->' . $field;
                            break;
                        }
                    }
                }
            }
            $this->setOption('viewMapping', $mapping);
        }
        return $mapping;
    }

    /**
     * 转换为数组. 视图模型不支持 hidden visible append
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->getData();
        foreach ($data as $name => &$val) {
            if ($val instanceof Modelable || $val instanceof Collection) {
                $val = $val->toArray();
            }
        }
        return $data;
    }

    /**
     * 设置视图模型数据
     *
     * @param array|object $data 数据
     * @return $this
     */
    public function data(array | object $data)
    {
        // 处理对象数据
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        // 数据验证
        $data = $this->validate($data);
        foreach ($this->getEntityProperties() as $field) {
            $this->$field = $data[$field] ?? null;
        }
        return $this;
    }

    /**
     * 刷新模型数据.
     *
     * @return $this
     */
    public function refresh()
    {
        $this->initData();
        return $this;
    }

    /**
     * 清空视图模型数据
     *
     * @return $this
     */
    public function clear()
    {
        foreach ($this->getEntityProperties() as $field) {
            $this->$field = null;
        }
        $this->exists(false);
        return $this;
    }

    /**
     * 获取视图模型数据
     *
     * @return array
     */
    protected function getData(): array
    {
        $data = [];
        foreach ($this->getEntityProperties() as $field) {
            $data[$field] = $this->$field;
        }
        return $data;
    }

    /**
     * 设置需要附加的输出属性.（视图模型下无效）
     *
     * @param array $append 属性列表
     *
     * @return $this
     */
    public function append(array $append)
    {
        return $this;
    }

    /**
     * 设置需要隐藏的输出属性.（视图模型下无效）
     *
     * @param array $hidden 属性列表
     *
     * @return $this
     */
    public function hidden(array $hidden)
    {
        return $this;
    }

    /**
     * 设置需要输出的属性.（视图模型下无效）
     *
     * @param array $visible
     *
     * @return $this
     */
    public function visible(array $visible)
    {
        return $this;
    }

    /**
     * 判断数据是否为空.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->model()->isEmpty();
    }

    /**
     * 获取克隆的模型实例.
     *
     * @return static
     */
    public function clone()
    {
        $model = new static();
        return $model->setModel($this->model());
    }

    /**
     *  设置模型.
     *
     * @param Model $model 模型对象
     * @return $this
     */
    public function setModel(Model $model)
    {
        parent::setModel($model);
        $this->initData();
        return $this;
    }

    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * 设置关联数据.
     *
     * @param string $relation 关联属性
     * @param Model  $model  关联数据
     *
     * @return void
     */
    public function setRelation($relation, $model)
    {
        $this->model()->setRelation($relation, $model);
    }

    /**
     * 设置关联绑定数据
     *
     * @param Model  $model 关联对象
     * @param array  $bind  绑定属性
     * @param string $relation  关联名称
     * @return void
     */
    public function bindRelationAttr($model, $bind, $relation) 
    {
        if ($relation) {
            $this->setRelation($relation, $model);
        }
    }

    /**
     * 视图模型数据转换为模型数据（用于写入 暂不支持子关联写入）.
     *
     * @return array
     */
    protected function convertData(): array
    {
        // 获取属性映射
        $properties = $this->getEntityPropertiesMap();
        $data       = $this->getData();
        $item       = [];
        $together   = [];
        $array      = [];
        foreach ($properties as $key => $field) {
            if (strpos($field, '->')) {
                if (!isset($data[$key]) || substr_count($field, '->') > 1) {
                    // 排除空值 以及 多级关联属性值
                    continue;
                }
                [$relation, $field] = explode('->', $field);
                if ('json' == $this->model()->getFieldType($relation)) {
                    // JSON数据赋值
                    $array[$relation][$field] = $data[$key];
                } else {
                    // 关联数据赋值
                    $together[] = $relation;
                    if ($this->model()->hasData($relation)) {
                        // 关联更新
                        $this->model()->$relation->$field = $data[$key];
                    } else {
                        // 新增关联
                        $array[$relation][$field] = $data[$key];
                    }
                }
            } else {
                $value =  $data[is_int($key) ? $field : $key];
                if (isset($value)) {
                    $item[$field] = $value;
                }
            }
        }

        // 关联数据或JSON数据封装
        foreach ($array as $relation => $val) {
            $this->model()->$relation = $val;
        }

        if (!empty($together)) {
            // 自动关联写入
            $this->model()->together(array_unique($together));
        }
        return $item;
    }

    /**
     * 验证视图模型数据. 
     *
     * @param array $data 数据
     * @param array $allow 需要验证的字段
     *
     * @throws ValidateException
     * @return array
     */
    protected function validate(array $data = [], array $allow = []): array
    {
        $validater = $this->getOption('validate');
        $data      = $data ?: $this->getData();
        if (!empty($validater)) {
            return validate($validater)
                ->only($allow ?: array_keys($data))
                ->checked($data);
        }
        return $data;
    }

    /**
     * 设置数据是否存在.
     *
     * @param bool $exists
     *
     * @return $this
     */
    public function exists(bool $exists = true)
    {
        return $this->setOption('exists', $exists);
    }

    /**
     * 判断数据是否存在数据库.
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->getOption('exists', false);
    }

    /**
     * 保存模型实例数据.
     *
     * @param array|object $data 数据
     * @param mixed $where 更新条件 true为强制新增
     * @param bool  $refresh  是否刷新数据
     * @return bool
     */
    public function save(array | object $data = [], $where = [], bool $refresh = false): bool
    {
        if ($data) {
            $this->data($data);
        }

        // 根据映射关系转换为实际模型数据
        $data = $this->convertData();
        // 处理自动时间字段数据
        foreach ($this->model()->getAutoTimeFields() as $field) {
            unset($data[$field]);
        }

        $result = $this->model()
            ->exists($this->isExists())
            ->save($data, $where, $refresh);

        if ($result) {
            // 刷新数据
            $this->refresh();
        }
        return $result;
    }

    /**
     * 删除模型数据.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->model()->delete()) {
            $this->clear();
            return true;
        }

        return false;
    }

    /**
     * 写入数据.
     *
     * @param array|object  $data 数据
     * @return static
     */
    public static function create(array | object $data)
    {
        $entity = new static();
        $entity->exists(false)->save($data, true);
        return $entity;
    }

    /**
     * 更新数据.
     *
     * @param array|object  $data 数据
     * @param mixed  $where       更新条件
     * @return static
     */
    public static function update(array | object $data, $where = [])
    {
        $entity = new static();
        $entity->exists(true)->save($data, $where, true);
        return $entity;
    }

    /**
     * 数据集写入
     *
     * @param iterable $dataSet 数据集
     * @param bool     $replace 是否replace
     *
     * @return Collection
     */
    public static function saveAll(iterable $dataSet, bool $replace = true): Collection
    {
        $collection = [];
        foreach ($dataSet as $data) {
            $entity = new static();
            $pk     = $entity->getPk();
            if ($replace) {
                $exists = true;
                foreach ((array) $pk as $field) {
                    if (is_string($field) && !isset($data[$field])) {
                        $exists = false;
                    }
                }
                $entity->exists($exists);
            }
            $entity->save($data, !$replace, true);
            $collection[] = $entity;
        }
        return new Collection($collection);
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
        if (property_exists($this, $name)) {
            return $this->$name ?? null;
        }
        return $this->model()->$name;
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
        if (property_exists($this, $name)) {
            $this->$name = $value;
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
        return !is_null($this->__get($name));
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
        unset($this->$name);
    }

    public function __debugInfo()
    {
        return [];
    }

    /**
     * 克隆模型实例
     * 
     * @return void
     */
    public function __clone()
    {
    }

    /**
     * 序列化模型对象
     * 
     * @return array
     */
    public function __serialize(): array
    {
        return $this->getData();
    }

    /**
     * 反序列化模型对象
     * 
     * @param array $data 
     * @return void
     */
    public function __unserialize(array $data) 
    {
        foreach ($data as $name => $val) {
            $this->$name = $val;
        }
    }

    public static function __callStatic($method, $args)
    {
        $entity = new static();
        $model  = $entity->model();
        if (in_array($method, ['destroy'])) {
            $db = $model;
        } else {
            // 处理映射字段的查询
            $map   = $entity->parseAutoMapping();
            $alias = Str::snake(class_basename($model));
            $db    = $model->db()->alias($alias)->via($alias)->fieldMap($map);
        }

        $auto   = $entity->getOption('autoMapping');
        if (!empty($auto) && !in_array(strtolower($method), ['with','withjoin'])) {
            // 自动关联查询
            $db->with($auto);
        }

        return call_user_func_array([$db, $method], $args);
    }

    public function __call($method, $args)
    {
        // 调用Model类方法
        $result = call_user_func_array([$this->model(), $method], $args);
        return $result instanceof Model ? $this : $result;
    }
}
