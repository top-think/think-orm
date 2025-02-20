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

use BackedEnum;
use Closure;
use InvalidArgumentException;
use Stringable;
use think\db\Express;
use think\db\Raw;
use think\helper\Str;
use think\model\contract\EnumTransform;
use think\model\contract\FieldTypeTransform;
use think\model\contract\Typeable;
use think\model\contract\Modelable;
use think\model\type\Date;
use think\model\type\DateTime;
use think\model\type\Json;

/**
 * 模型数据处理.
 */
trait Attribute
{
    /**
     * 初始化模型数据.
     *
     * @param array|object $data 实体模型数据
     * @param bool  $fromSave
     *
     * @return void
     */
    private function initializeData(array | object $data, bool $fromSave = false)
    {
        // 分析数据
        $data    = $this->parseData($data);
        $schema  = $this->getFields();
        $fields  = array_keys($schema);
        $mapping = $this->getOption('mapping');

        // 实体模型赋值
        foreach ($data as $name => $val) {
            if (in_array($name, $this->getOption('disuse'))) {
                // 废弃字段
                continue;
            }

            if (!empty($mapping)) {
                $name = array_search($name, $mapping) ?: $name;
            }

            if (str_contains($name, '__')) {
                // 组装关联JOIN查询数据
                [$relation, $attr] = explode('__', $name, 2);

                $relations[$relation][$attr] = $val;
                continue;
            }

            $trueName = $this->getRealFieldName($name);
            if ($this->isView() || $this->isVirtual() || in_array($trueName, $fields)) {
                // 读取数据后进行类型转换
                $value = $this->readTransform($val, $schema[$trueName] ?? 'string');
                // 数据赋值
                $this->setData($trueName, $value);
                // 记录原始数据
                $origin[$trueName] = $value;
            }
        }

        if (!empty($relations)) {
            // 设置关联数据
            $this->parseRelationData($relations);
        }

        if (!empty($origin) && !$fromSave) {
            $this->trigger('AfterRead');
            $this->setOption('origin', $origin);
            $this->setOption('get', []);
        }
    }
        
    /**
     * 获取主键名.
     *
     * @return string|array
     */
    public function getPk()
    {
        return $this->getOption('pk');
    }

    /**
     * 获取表名（不含前后缀）.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->getOption('name', Str::snake(class_basename(static::class)));
    }

    /**
     * 解析模型数据.
     *
     * @param array|object $data 数据
     *
     * @return array
     */
    private function parseData(array | object $data): array
    {
        if ($data instanceof self) {
            $data = $data->getData();
        } elseif (is_object($data)) {
            $data = get_object_vars($data);
        }

        return $data;
    }

    /**
     * 设置数据字段获取器.
     *
     * @param array $attr     字段获取器定义
     *
     * @return $this
     */
    public function withFieldAttr(array $attr)
    {
        foreach ($attr as $name => $closure) {
            $this->withAttr($name, $closure);
        }

        return $this;
    }

    /**
     * 动态设置字段获取器.
     *
     * @param string    $name     字段名
     * @param callable  $callback 闭包获取器
     *
     * @return $this
     */
    public function withAttr(string $name, callable $callback)
    {
        $name = $this->getRealFieldName($name);

        $this->setWeakData('with_attr', $name, $callback);
        // 自动追加输出
        self::$weakMap[$this]['append'][] = $name;
        return $this;
    }

    /**
     * 获取实际字段名.
     * 严格模式下 完全和数据表字段对应一致（默认）
     * 非严格模式 统一转换为snake规范（支持驼峰规范读取）
     *
     * @param string $name  字段名
     *
     * @return mixed
     */
    protected function getRealFieldName(string $name)
    {
        if (false === $this->getOption('strict')) {
            return Str::snake($name);
        }

        return $name;
    }

    /**
     * 数据读取 类型转换.
     *
     * @param mixed        $value 值
     * @param string|arrau $type  要转换的类型
     *
     * @return mixed
     */
    protected function readTransform($value, string | array $type)
    {
        if (is_null($value)) {
            return;
        }

        if ($value instanceof Raw || $value instanceof Express) {
            return $value;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (str_contains($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        $typeTransform = static function (string $type, $value, $model) {
            if (class_exists($type) && !($value instanceof $type)) {
                if (is_subclass_of($type, Typeable::class)) {
                    $value = $type::from($value, $model);
                } elseif (is_subclass_of($type, FieldTypeTransform::class)) {
                    $value = $type::get($value, $model);
                } elseif (is_subclass_of($type, BackedEnum::class)) {
                    $value = $type::from($value);
                } else {
                    // 对象类型
                    $value = new $type($value);
                }
            }
            return $value;
        };

        return match ($type) {
            'string' => (string) $value,
            'int'       => (int) $value,
            'float'     => empty($param) ? (float) $value : (float) number_format($value, (int) $param, '.', ''),
            'bool'      => (bool) $value,
            'array'     => empty($value) ? [] : json_decode($value, true),
            'object'    => empty($value) ? new \stdClass() : json_decode($value),
            'json'      => $typeTransform(Json::class, $value, $this),
            'date'      => $typeTransform(Date::class, $value, $this),
            'datetime'  => $typeTransform(DateTime::class, $value, $this),
            'timestamp' => $typeTransform(DateTime::class, $value, $this),
            default     => $typeTransform($type, $value, $this),
        };
    }

    /**
     * 数据写入 类型转换.
     *
     * @param mixed        $value 值
     * @param string|array $type  要转换的类型
     *
     * @return mixed
     */
    protected function writeTransform($value, string | array $type)
    {
        if (null === $value) {
            return;
        }

        if ($value instanceof Raw || $value instanceof Express) {
            return $value;
        }

        if (is_array($type)) {
            [$type, $param] = $type;
        } elseif (str_contains($type, ':')) {
            [$type, $param] = explode(':', $type, 2);
        }

        $typeTransform = static function (string $type, $value, $model) {
            if (class_exists($type)) {
                if (is_subclass_of($type, Typeable::class)) {
                    $value = $value->value();
                } elseif (is_subclass_of($type, FieldTypeTransform::class)) {
                    $value = $type::set($value, $model);
                } elseif ($value instanceof BackedEnum) {
                    $value = $value->value;
                } elseif ($value instanceof Stringable) {
                    $value = $value->__toString();
                }
            }
            return $value;
        };

        return match ($type) {
            'string'    => (string) $value,
            'int'       => (int) $value,
            'float'     => empty($param) ? (float) $value : (float) number_format($value, (int) $param, '.', ''),
            'bool'      => (bool) $value,
            'object'    => is_object($value) ? json_encode($value, JSON_FORCE_OBJECT) : $value,
            'array'     => json_encode((array) $value, JSON_UNESCAPED_UNICODE),
            'json'      => $typeTransform(Json::class, $value, $this),
            'date'      => $typeTransform(Date::class, $value, $this),
            'datetime'  => $typeTransform(DateTime::class, $value, $this),
            'timestamp' => $typeTransform(DateTime::class, $value, $this),
            default     => $typeTransform($type, $value, $this),
        };
    }

    /**
     * 刷新对象原始数据（为当前数据）.
     *
     * @return $this
     */
    public function refreshOrigin()
    {
        $this->setOption('origin', $this->getData());

        return $this;
    }

    /**
     * 设置主键值
     *
     * @param int|string $value 值
     * @return void
     */
    public function setKey($value)
    {
        $pk = $this->getPk();

        if (is_string($pk)) {
            $this->set($pk, $value);
        }
    }

    /**
     * 获取主键值
     *
     * @return mixed
     */
    public function getKey()
    {
        $pk = $this->getPk();
        if (is_string($pk)) {
            return $this->get($pk);
        }

        foreach ($pk as $name) {
            $data[$name] = $this->get($name);
        }
        return $data;
    }

    /**
     * 重置模型数据.
     *
     * @param array $data
     *
     * @return void
     */
    public function data(array $data)
    {
        $this->initializeData($data);
    }

    /**
     * 获取模型实际数据.
     *
     * @param string|null $name 字段名
     * @return mixed
     */
    public function getData(?string $name = null)
    {
        if ($name) {
            $name = $this->getRealFieldName($name);
            return $this->getWeakData('data', $name);
        }
        return $this->getOption('data');
    }

    /**
     * 设置数据对象的实际值
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return void
     */
    protected function setData(string $name, $value)
    {
        $this->setWeakData('data', $name, $value);
        if ($this->getWeakData('get', $name)) {
            $this->setWeakData('get', $name, null);
        }
    }

    /**
     * 清空模型数据.
     *
     * @return $this
     */
    public function clear()
    {
        $this->setOption('data', []);
        $this->setOption('origin', []);
        $this->setOption('get', []);
        $this->setOption('relation', []);
        return $this;
    }

    /**
     * 获取原始数据.
     *
     * @param string|null $name 字段名
     * @return mixed
     */
    public function getOrigin(?string $name = null)
    {
        if ($name) {
            $name = $this->getRealFieldName($name);
            return $this->getWeakData('origin', $name);
        }
        return $this->getOption('origin');
    }

    /**
     * 判断数据是否为空.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->getData());
    }

    /**
     * 判断JSON数据是否为数组格式.
     *
     * @return bool
     */
    public function isJsonAssoc(): bool
    {
        return $this->getOption('jsonAssoc', false);
    }

    /**
     * 设置数据对象的值 并进行类型自动转换
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return $this
     */
    public function set(string $name, $value)
    {
        $ignore = $this->getOption('ignore', []);
        $name   = $this->getMappingName($name);
        $type   = $this->getFields($name);

        if (in_array($name, $ignore)) {
            // 忽略属性
            return $this;
        }

        if (is_null($value) && is_subclass_of($type, Entity::class)) {
            // 关联数据为空 设置一个空模型
            $value = new $type();
        } elseif (!($value instanceof Modelable || $value instanceof Collection)) {
            // 类型自动转换
            $value = $this->readTransform($value, $type);
        }

        $this->setData($name, $value);
        return $this;
    }

    /**
     * 使用修改器或类型自动转换处理数据（写入数据前自动调用）
     *
     * @param string $name  名称
     * @param mixed  $value 值
     * @param array  $data 所有数据
     *
     * @return mixed
     */
    private function setWithAttr(string $name, $value, array $data = [])
    {
        $attr   = Str::studly($name);
        $method = 'set' . $attr . 'Attr';
        if (method_exists($this, $attr) && $set = $this->$attr()['set'] ?? '') {
            $value = $set($value, $data);
        } elseif (method_exists($this, $method)) {
            $value = $this->$method($value, $data);
        } else {
            // 类型转换
            $value = $this->writeTransform($value, $this->getFields($name));
        }

        if ($value instanceof Express) {
            // 处理运算表达式
            $step   = $value->getStep();
            $origin = $this->getOrigin($name);
            $real   = match ($value->getType()) {
                '+'         => $origin + $step,
                '-'         => $origin - $step,
                '*'         => $origin * $step,
                '/'         => $origin / $step,
                default     => $origin,
            };
            $this->setData($name, $real);
        } elseif (is_scalar($value)) {
            // 同步写入修改器或类型自动转换结果
            $this->setData($name, $value);
        }

        return $value;
    }

    /**
     * 获取数据对象的值（支持使用获取器）
     *
     * @param string $name 名称
     * @param bool   $attr 是否使用获取器
     *
     * @return mixed
     */
    public function get(string $name, bool $attr = true)
    {
        $ignore = $this->getOption('ignore', []);
        $name   = $this->getMappingName($name);
        if (in_array($name, $ignore)) {
            // 忽略属性
            return null;
        }

        if ($attr && $value = $this->getWeakData('get', $name)) {
            // 已经输出的数据直接返回
            return $value;
        }

        if (!array_key_exists($name, $this->getOption('data'))) {
            // 动态获取关联数据
            $value = $this->getRelationData($name) ?: null;
        } else {
            $value = $this->getData($name);
        }

        if ($attr) {
            // 通过获取器输出
            $value = $this->getWithAttr($name, $value, $this->getData());
            $this->setWeakData('get', $name, $value);
        }

        return $value;
    }

    /**
     * 获取映射字段
     *
     * @param string $name 名称
     *
     * @return string
     */
    protected function getMappingName(string $name): string
    {
        $mapping = $this->getOption('mapping');
        if (!empty($mapping)) {
            $name = array_search($name, $mapping) ?: $name;
        }
        return $this->getRealFieldName($name);
    }

    /**
     * 处理数据对象的值（经过获取器和类型转换）
     *
     * @param string $name 名称
     * @param mixed  $value 值
     * @param array  $data 所有数据
     *
     * @return mixed
     */
    private function getWithAttr(string $name, $value, array $data = [])
    {
        $attr     = Str::studly($name);
        $method   = 'get' . $attr . 'Attr';
        $withAttr = $this->getWeakData('with_attr', $name);
        if ($withAttr) {
            // 动态获取器
            $value = $withAttr($value, $data, $this);
        } elseif (method_exists($this, $attr) && $get = $this->$attr()['get'] ?? '') {
            // 属性器
            $value = $get($value, $data);
        } elseif (method_exists($this, $method)) {
            // 获取器
            $value = $this->$method($value, $data);
        } elseif ($value instanceof Typeable || is_subclass_of($value, EnumTransform::class)) {
            // 类型自动转换
            $value = $value->value();
        }
        return $value;
    }

    /**
     * 设置忽略属性.
     *
     * @param array $ignore 忽略属性列表
     *
     * @return $this
     */
    public function ignore(array $ignore)
    {
        $this->setOption('ignore', $ignore);

        return $this;
    }    
}
