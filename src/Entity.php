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
use JsonSerializable;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\model\contract\Modelable;
use WeakMap;

/**
 * Class Entity.
 */
abstract class Entity implements JsonSerializable, ArrayAccess, Arrayable, Jsonable, Modelable
{
    private static ?WeakMap $weakMap = null;

    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     */
    public function __construct(?Model $model = null)
    {
        if (!self::$weakMap) {
            self::$weakMap = new WeakMap;
        }

        // 获取实体模型参数
        $options = $this->getOptions();

        if (is_null($model)) {
            $class = !empty($options['model_class']) ? $options['model_class'] : str_replace('\\entity\\', '\\model\\', static::class);
            $model = new $class();
            unset($options['model_class']);
        }

        $model->setOptions($options);

        self::$weakMap[$this] = [
            'model' =>  $model,
        ];

        // 初始化模型
        $this->init();        
    }

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
     *  初始化模型.
     *
     * @return void
     */
    protected function init(): void {}

    /**
     * 获取模型对象实例.
     * @return Model
     */
    public function model()
    {
        return self::$weakMap[$this]['model'];
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
        return $this->model()->get($name);
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
        $this->model()->set($name, $value);
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
        return $this->model()->__isset($name);
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
        $this->model()->__unset($name);
    }

    public function __toString()
    {
        return $this->model()->toJson();
    }

    public function __debugInfo()
    {
        return $this->model()->getData();
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->model()->toArray();
    }

    /**
     * 模型数据转数组.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->model()->toArray();
    }   
     
    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function tojson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return $this->model()->toJson($options);
    }

    // ArrayAccess
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->__set($name, $value);
    }

    public function offsetGet(mixed $name): mixed
    {
        return $this->__get($name);
    }

    public function offsetExists(mixed $name): bool
    {
        return $this->__isset($name);
    }

    public function offsetUnset(mixed $name): void
    {
        $this->__unset($name);
    }

    public static function __callStatic($method, $args)
    {
        $entity = new static();
        if (in_array($method, ['destroy', 'create', 'update'])) {
            // 调用model的静态方法
            $db = $entity->model();
        } else {
            // 调用Query类查询方法
            $db = $entity->getQuery();
        }

        return call_user_func_array([$db, $method], $args);
    }

    public function __call($method, $args)
    {
        // 调用Model类方法
        return call_user_func_array([$this->model(), $method], $args);
    }
}
