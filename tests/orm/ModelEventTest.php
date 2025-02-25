<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelEventTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_event_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_event_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_event_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'status' => 1],
            ['id' => 2, 'name' => 'test2', 'status' => 0],
        ];
    }

    public function testInsertEvents()
    {
        $beforeInsertCalled = false;
        $afterInsertCalled = false;

        $model = new class extends Model {
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
        };

        $data = ['name' => 'test3', 'status' => 1];
        $result = $model::create($data);

        $this->assertTrue($beforeInsertCalled, 'before_insert event not triggered');
        $this->assertTrue($afterInsertCalled, 'after_insert event not triggered');
        $this->assertEquals('modified_test3', $result->name);
    }

    public function testUpdateEvents()
    {
        $beforeUpdateCalled = false;
        $afterUpdateCalled = false;

        $model = new class extends Model {
            protected $table = 'test_event_model';
            protected $autoWriteTimestamp = true;

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
        };

        // 先创建一条记录
        $record = $model::create(['name' => 'test4', 'status' => 1]);

        // 更新记录
        $record->name = 'new_name';
        $record->save();

        $this->assertTrue($beforeUpdateCalled, 'before_update event not triggered');
        $this->assertTrue($afterUpdateCalled, 'after_update event not triggered');
        $this->assertEquals('updated_new_name', $record->name);
    }

    public function testDeleteEvents()
    {
        $beforeDeleteCalled = false;
        $afterDeleteCalled = false;

        $model = new class extends Model {
            protected $table = 'test_event_model';
            protected $autoWriteTimestamp = true;

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
        };

        // 创建两条记录
        $record1 = $model::create(['name' => 'test5', 'status' => 1]);
        $record2 = $model::create(['name' => 'test6', 'status' => 0]);

        // 尝试删除状态为1的记录
        $record1->delete();
        $this->assertTrue($beforeDeleteCalled, 'before_delete event not triggered');
        $this->assertTrue($afterDeleteCalled, 'after_delete event not triggered');

        // 重置标志
        $beforeDeleteCalled = false;
        $afterDeleteCalled = false;

        // 尝试删除状态为0的记录
        $record2->delete();
        $this->assertTrue($beforeDeleteCalled, 'before_delete event not triggered');
        $this->assertFalse($afterDeleteCalled, 'after_delete event should not be triggered');

        // 验证记录2仍然存在
        $this->assertNotNull($model::find($record2->id));
    }

    public function testWriteEvents()
    {
        $beforeWriteCalled = false;
        $afterWriteCalled = false;

        $model = new class extends Model {
            protected $table = 'test_event_model';
            protected $autoWriteTimestamp = true;

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
        };

        // 测试插入时的写入事件
        $record = $model::create(['name' => 'test7', 'status' => 1]);
        $this->assertTrue($beforeWriteCalled, 'before_write event not triggered on insert');
        $this->assertTrue($afterWriteCalled, 'after_write event not triggered on insert');
        $this->assertEquals('write_test7', $record->name);

        // 重置标志
        $beforeWriteCalled = false;
        $afterWriteCalled = false;

        // 测试更新时的写入事件
        $record->name = 'test8';
        $record->save();
        $this->assertTrue($beforeWriteCalled, 'before_write event not triggered on update');
        $this->assertTrue($afterWriteCalled, 'after_write event not triggered on update');
        $this->assertEquals('write_test8', $record->name);
    }

    public function testModelObserver()
    {
        $observer = new class {
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
        };

        $model = new class extends Model {
            protected $table = 'test_event_model';
            protected $autoWriteTimestamp = true;
            protected $eventObserver;

            public function __construct(array $data = [])
            {
                global $observer;
                $this->eventObserver = $observer;
                parent::__construct($data);
            }
        };

        // 测试插入事件
        $data = ['name' => 'test9', 'status' => 1];
        $result = $model::create($data);

        $this->assertTrue($observer->beforeInsertCalled, 'observer before_insert event not triggered');
        $this->assertTrue($observer->afterInsertCalled, 'observer after_insert event not triggered');
        $this->assertTrue($observer->beforeWriteCalled, 'observer before_write event not triggered');
        $this->assertTrue($observer->afterWriteCalled, 'observer after_write event not triggered');
        $this->assertEquals('observer_test9', $result->name);

        // 重置标志
        $observer->beforeWriteCalled = false;
        $observer->afterWriteCalled = false;

        // 测试更新事件
        $result->name = 'test10';
        $result->save();

        $this->assertTrue($observer->beforeUpdateCalled, 'observer before_update event not triggered');
        $this->assertTrue($observer->afterUpdateCalled, 'observer after_update event not triggered');
        $this->assertTrue($observer->beforeWriteCalled, 'observer before_write event not triggered');
        $this->assertTrue($observer->afterWriteCalled, 'observer after_write event not triggered');
        $this->assertEquals('observer_updated_test10', $result->name);

        // 测试删除事件
        // 创建两条记录用于测试
        $record1 = $model::create(['name' => 'test11', 'status' => 1]);
        $record2 = $model::create(['name' => 'test12', 'status' => 0]);

        // 重置标志
        $observer->beforeDeleteCalled = false;
        $observer->afterDeleteCalled = false;

        // 尝试删除状态为1的记录
        $record1->delete();
        $this->assertTrue($observer->beforeDeleteCalled, 'observer before_delete event not triggered');
        $this->assertTrue($observer->afterDeleteCalled, 'observer after_delete event not triggered');

        // 重置标志
        $observer->beforeDeleteCalled = false;
        $observer->afterDeleteCalled = false;

        // 尝试删除状态为0的记录
        $record2->delete();
        $this->assertTrue($observer->beforeDeleteCalled, 'observer before_delete event not triggered');
        $this->assertFalse($observer->afterDeleteCalled, 'observer after_delete event should not be triggered');

        // 验证记录2仍然存在
        $this->assertNotNull($model::find($record2->id));
    }
}