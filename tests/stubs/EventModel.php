<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;

class EventModel extends Model
{
    protected $table = 'test_event_model';
    protected $autoWriteTimestamp = true;

    public function onBeforeInsert($model)
    {
        global $beforeInsertCalled;
        $beforeInsertCalled = true;
        // 在插入前修改数据
        $model->name = 'modified_' . $model->name;
    }

    public function onAfterInsert($model)
    {
        global $afterInsertCalled;
        $afterInsertCalled = true;
    }

    public function onBeforeUpdate($model)
    {
        global $beforeUpdateCalled;
        $beforeUpdateCalled = true;
        // 在更新前修改数据
        $model->name = 'updated_' . $model->name;
    }

    public function onAfterUpdate($model)
    {
        global $afterUpdateCalled;
        $afterUpdateCalled = true;
    }

    public function onBeforeDelete($model)
    {
        global $beforeDeleteCalled;
        $beforeDeleteCalled = true;
        // 可以在删除前执行一些验证
        if ($model->status === 0) {
            return false; // 阻止删除
        }
    }

    public function onAfterDelete($model)
    {
        global $afterDeleteCalled;
        $afterDeleteCalled = true;
    }

    public function onBeforeWrite($model)
    {
        global $beforeWriteCalled;
        $beforeWriteCalled = true;
        // 在写入前修改数据
        $model->name = 'write_' . $model->name;
    }

    public function onAfterWrite($model)
    {
        global $afterWriteCalled;
        $afterWriteCalled = true;
    }
}