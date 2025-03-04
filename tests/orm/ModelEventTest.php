<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use tests\stubs\EventModel;
use tests\stubs\EventObserver;
use tests\stubs\ObservedModel;

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
        EventModel::resetEventFlags();
        $data = ['name' => 'test3', 'status' => 1];
        $result = EventModel::create($data);

        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeInsertCalled'], 'before_insert event not triggered');
        $this->assertTrue($flags['afterInsertCalled'], 'after_insert event not triggered');
        $this->assertEquals('modified_write_test3', $result->name);
    }

    public function testUpdateEvents()
    {
        // 先创建一条记录
        $record = EventModel::create(['name' => 'test4', 'status' => 1]);

        EventModel::resetEventFlags();
        // 更新记录
        $record->name = 'new_name';
        $record->save();

        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeUpdateCalled'], 'before_update event not triggered');
        $this->assertTrue($flags['afterUpdateCalled'], 'after_update event not triggered');
        $this->assertEquals('updated_write_new_name', $record->name);
    }

    public function testDeleteEvents()
    {
        // 创建两条记录
        $record1 = EventModel::create(['name' => 'test5', 'status' => 1]);
        $record2 = EventModel::create(['name' => 'test6', 'status' => 0]);

        // 尝试删除状态为1的记录
        EventModel::resetEventFlags();
        $record1->delete();
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeDeleteCalled'], 'before_delete event not triggered');
        $this->assertTrue($flags['afterDeleteCalled'], 'after_delete event not triggered');

        // 尝试删除状态为0的记录
        EventModel::resetEventFlags();
        $record2->delete();
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeDeleteCalled'], 'before_delete event not triggered');
        $this->assertFalse($flags['afterDeleteCalled'], 'after_delete event should not be triggered');

        // 验证记录2仍然存在
        $this->assertNotNull(EventModel::find($record2->id));
    }

    public function testWriteEvents()
    {
        // 测试插入时的写入事件
        EventModel::resetEventFlags();
        $record = EventModel::create(['name' => 'test7', 'status' => 1]);
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeWriteCalled'], 'before_write event not triggered on insert');
        $this->assertTrue($flags['afterWriteCalled'], 'after_write event not triggered on insert');
        $this->assertEquals('modified_write_test7', $record->name);

        // 测试更新时的写入事件
        EventModel::resetEventFlags();
        $record->name = 'test8';
        $record->save();
        $flags = EventModel::getEventFlags();
        $this->assertTrue($flags['beforeWriteCalled'], 'before_write event not triggered on update');
        $this->assertTrue($flags['afterWriteCalled'], 'after_write event not triggered on update');
        $this->assertEquals('updated_write_test8', $record->name);
    }

    public function testModelObserver()
    {
        // 测试插入事件
        $data = ['name' => 'test9', 'status' => 1];
        $result = ObservedModel::create($data);

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
        $record1 = ObservedModel::create(['name' => 'test11', 'status' => 1]);
        $record2 = ObservedModel::create(['name' => 'test12', 'status' => 0]);

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
        $this->assertNotNull(ObservedModel::find($record2->id));
    }
}