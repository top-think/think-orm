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

use think\db\BaseQuery as Query;
use think\db\exception\DbException as Exception;
use think\facade\Db;

/**
 * 数据库连接.
 */
trait DbConnect
{
    /**
     * 设置Db对象实例.（用于兼容）
     */
    public static function setDb($db)
    {

    }

    /**
     * 获取Db对象实例.
     * @return Query
     */
    public function db()
    {
        return $this->getOption('db')->schema($this->getOption('schema'))->model($this);
    }

    /**
     * 初始化数据库连接对象.
     *
     * @return void
     */
    private function initDb()
    {
        $connection = $this->getOption('connection');
        if ($this->getOption('db')) {
            $db = $this->getOption('db')->connect($connection);
        } else {
            $db = Db::connect($connection);
        }

        $db = $db->name($this->getName())->pk($this->getOption('pk'));

        if ($this->getOption('table')) {
            $db->table($this->getOption('table'));
        } else {
            $db->suffix($this->getOption('suffix'));
        }

        $this->setOption('db', $db);
    }

    /**
     * 获取数据表字段类型列表（或某个字段的类型）.
     *
     * @param string|null $field 字段名
     *
     * @return array|string
     */
    protected function getFields(?string $field = null)
    {
        $schema = $this->getOption('schema');
        if (empty($schema)) {
            if ($this->isView() || $this->isVirtual()) {
                $schema = $this->getOption('type', []);
            } else {
                // 获取数据表信息
                $model  = $this->getOption('db');
                $fields = $model->getFieldsType();
                $schema = array_merge($fields, $this->getOption('type', $model->getType()));
            }

            $this->setOption('schema', $schema);
        }

        if ($field) {
            return $schema[$field] ?? 'string';
        }

        return $schema;
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
     * 新增数据是否使用Replace.
     *
     * @param bool $replace
     *
     * @return $this
     */
    public function replace(bool $replace = true)
    {
        $this->db()->replace($replace);

        return $this;
    }

    /**
     * 获取当前模型的数据表后缀
     *
     * @return string
     */
    public function getSuffix(): string
    {
        return $this->getOption('suffix', '');
    }

    /**
     * 设置当前模型数据表的后缀
     *
     * @param string $suffix 数据表后缀
     *
     * @return $this
     */
    public function setSuffix(string $suffix)
    {
        $this->setOption('suffix', $suffix);

        return $this;
    }

    /**
     * 构建实体模型查询.
     *
     * @param Query $query 查询对象
     * @return void
     */
    protected function query(Query $query) {}

    /**
     * 获取查询对象
     *
     * @return Query
     */
    public function getQuery(): Query
    {
        $query = $this->db();

        // 执行扩展查询
        $this->query($query->suffix($this->getOption('suffix')));
        return $query;
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();

        if ($model->isVirtual()) {
            throw new Exception('virtual model not support db query');
        }

        $db = $model->getQuery();

        if (!empty(self::$weakMap[$model]['autoRelation'])) {
            // 自动获取关联数据
            $db->with(self::$weakMap[$model]['autoRelation']);
        }

        return call_user_func_array([$db, $method], $args);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->getQuery(), $method], $args);
    }    
}
