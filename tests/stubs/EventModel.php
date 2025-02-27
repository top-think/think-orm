<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class EventModel extends Model
{
    protected $table = 'test_event_model';
    protected $autoWriteTimestamp = true;

    protected static $beforeInsertCalled = false;
    protected static $afterInsertCalled = false;
    protected static $beforeUpdateCalled = false;
    protected static $afterUpdateCalled = false;
    protected static $beforeDeleteCalled = false;
    protected static $afterDeleteCalled = false;
    protected static $beforeWriteCalled = false;
    protected static $afterWriteCalled = false;

    public static function resetEventFlags(): void
    {
        self::$beforeInsertCalled = false;
        self::$afterInsertCalled = false;
        self::$beforeUpdateCalled = false;
        self::$afterUpdateCalled = false;
        self::$beforeDeleteCalled = false;
        self::$afterDeleteCalled = false;
        self::$beforeWriteCalled = false;
        self::$afterWriteCalled = false;
    }

    public static function getEventFlags(): array
    {
        return [
            'beforeInsertCalled' => self::$beforeInsertCalled,
            'afterInsertCalled' => self::$afterInsertCalled,
            'beforeUpdateCalled' => self::$beforeUpdateCalled,
            'afterUpdateCalled' => self::$afterUpdateCalled,
            'beforeDeleteCalled' => self::$beforeDeleteCalled,
            'afterDeleteCalled' => self::$afterDeleteCalled,
            'beforeWriteCalled' => self::$beforeWriteCalled,
            'afterWriteCalled' => self::$afterWriteCalled,
        ];
    }

    public function onBeforeInsert($model)
    {
        self::$beforeInsertCalled = true;
        // 在插入前修改数据
        $model->name = 'modified_' . $model->name;
    }

    public function onAfterInsert($model)
    {
        self::$afterInsertCalled = true;
    }

    public function onBeforeUpdate($model)
    {
        self::$beforeUpdateCalled = true;
        // 在更新前修改数据
        $model->name = 'updated_' . $model->name;
    }

    public function onAfterUpdate($model)
    {
        self::$afterUpdateCalled = true;
    }

    public function onBeforeDelete($model)
    {
        self::$beforeDeleteCalled = true;
        // 可以在删除前执行一些验证
        if ($model->status === 0) {
            return false; // 阻止删除
        }
    }

    public function onAfterDelete($model)
    {
        self::$afterDeleteCalled = true;
    }

    public function onBeforeWrite($model)
    {
        self::$beforeWriteCalled = true;
        // 在写入前修改数据
        $model->name = 'write_' . $model->name;
    }

    public function onAfterWrite($model)
    {
        self::$afterWriteCalled = true;
    }
}