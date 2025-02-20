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
use Stringable;
use think\model\contract\Typeable;
use think\model\type\DateTime;

/**
 * 自动写入数据.
 */
trait AutoWriteData
{
    /**
     * 字段自动写入.
     *
     * @param array $data 数据
     * @param bool  $update 是否更新
     * @return void
     */
    protected function autoWriteData(array &$data, bool $update)
    {
        // 数据写入前置检查
        $this->checkData($data, $update);

        // 自动时间戳处理
        $this->autoDateTime($data, $update);

        $auto = $this->getOption($update ? 'auto_update' : 'auto_insert', []);
        foreach ($auto as $name => $val) {
            $field = is_string($name) ? $name : $val;
            if (!isset($data[$field])) {
                if ($val instanceof Closure) {
                    $value = $val($this);
                } else {
                    $value = is_string($name) ? $val : $this->setWithAttr($field, null, $data);
                }
                $data[$field] = $value;
                $this->setData($field, $value);
            }
        }
    }

    /**
     * 时间字段自动写入.
     *
     * @param array $data 数据
     * @param bool $update 是否更新
     * @return void
     */
    protected function autoDateTime(array &$data, bool $update)
    {
        $autoDateTime   = $this->getOption('auto_timestamp', true);
        if ($autoDateTime) {
            $dateTimeFields = [$this->getOption('update_time')];
            if (!$update) {
                array_unshift($dateTimeFields, $this->getOption('create_time'));
            }

            foreach ($dateTimeFields as $field) {
                if (is_string($field)) {
                    $data[$field] = $this->getDateTime($field);
                    $this->setData($field, $this->readTransform($data[$field], $this->getFields($field)));
                }
            }
        }
    }

    /**
     * 获取当前时间.
     *
     * @param string $field 字段名
     * @return void
     */
    protected function getDateTime(string $field)
    {
        $type = $this->getFields($field);
        if ('int' == $type) {
            $value = time();
        } elseif (is_subclass_of($type, Typeable::class)) {
            $value = $type::from('now', $this)->value();
        } elseif (str_contains($type, '\\')) {
            // 对象数据写入
            $obj = new $type();
            if ($obj instanceof Stringable) {
                // 对象数据写入
                $value = $obj->__toString();
            }
        } else {
            $value = DateTime::from('now', $this)->value();
        }
        return $value;
    }

    public function getAutoWriteTimestamp(): bool
    {
        return $this->getOption('auto_timestamp') ? true : false;
    }

    public function isAutoWriteTimestamp(bool $auto)
    {
        $this->setOption('auto_timestamp', $auto);
    }

    public function getDateFormat()
    {
        return $this->getOption('datetime_format');
    }

    public function setDateFormat(string $format)
    {
        $this->setOption('datetime_format', $format);
    }

    public function setTimeField($createTime, $updateTime)
    {
        $this->setOption('create_time', $createTime);
        $this->setOption('update_time', $updateTime);
    }    
}
