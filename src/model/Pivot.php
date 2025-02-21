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
declare(strict_types=1);

namespace think\model;

use think\Model;

/**
 * 多对多中间表模型类.
 */
class Pivot extends Model
{
    /**
     * 父模型.
     *
     * @var Model
     */
    public $parent;

    /**
     * 是否时间自动写入.
     *
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 架构函数.
     *
     * @param array      $data   数据
     * @param Model|null $parent 上级模型
     * @param string     $table  中间数据表名
     */
    public function __construct(array $data = [], ?Model $parent = null, string $table = '')
    {
        $this->parent = $parent;

        if (is_null($this->getOption('name')) {
            $this->setOption('name', $table);
        }

        parent::__construct($data);
    }

    /**
     * 创建新的模型实例.
     *
     * @param array $data    数据
     *
     * @return Model
     */
    public function newInstance(array $data = [])
    {
        $model = parent::newInstance($data);

        $model->parent = $this->parent;
        $model->setOption('name', $this->getOption('name'));

        return $model;
    }
}
