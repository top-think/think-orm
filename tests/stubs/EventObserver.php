<?php
declare(strict_types=1);

namespace tests\stubs;

class EventObserver
{
    public $beforeInsertCalled = false;
    public $afterInsertCalled = false;
    public $beforeUpdateCalled = false;
    public $afterUpdateCalled = false;
    public $beforeDeleteCalled = false;
    public $afterDeleteCalled = false;
    public $beforeWriteCalled = false;
    public $afterWriteCalled = false;

    public function onBeforeInsert($model)
    {
        $this->beforeInsertCalled = true;
        $model->name = 'observer_' . $model->name;
    }

    public function onAfterInsert($model)
    {
        $this->afterInsertCalled = true;
    }

    public function onBeforeUpdate($model)
    {
        $this->beforeUpdateCalled = true;
        $model->name = 'observer_updated_' . $model->name;
    }

    public function onAfterUpdate($model)
    {
        $this->afterUpdateCalled = true;
    }

    public function onBeforeDelete($model)
    {
        $this->beforeDeleteCalled = true;
        if ($model->status === 0) {
            return false;
        }
    }

    public function onAfterDelete($model)
    {
        $this->afterDeleteCalled = true;
    }

    public function onBeforeWrite($model)
    {
        $this->beforeWriteCalled = true;
    }

    public function onAfterWrite($model)
    {
        $this->afterWriteCalled = true;
    }
}