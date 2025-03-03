<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_model` (
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
        Db::execute('TRUNCATE TABLE `test_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'status' => 1],
            ['id' => 2, 'name' => 'test2', 'status' => 0],
            ['id' => 3, 'name' => 'test3', 'status' => 1],
        ];
    }

    public function testCreate()
    {
        $model = new class extends Model
        {
            protected $table              = 'test_model';
            protected $autoWriteTimestamp = true;
        };

        $data   = ['name' => 'test4', 'status' => 1];
        $result = $model::create($data);

        $this->assertInstanceOf(Model::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['status'], $result->status);
        $this->assertNotEmpty($result->create_time);
    }

    public function testUpdate()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = new class extends Model
        {
            protected $table              = 'test_model';
            protected $autoWriteTimestamp = true;
        };

        $updateData = ['id' => 1, 'name' => 'updated', 'status' => 0];
        $result     = $model::update($updateData);

        $this->assertInstanceOf(Model::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals($updateData['name'], $result->name);
        $this->assertEquals($updateData['status'], $result->status);
        $this->assertNotEmpty($result->update_time);
    }

    public function testDelete()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = new class extends Model
        {
            protected $table = 'test_model';
        };

        $result = $model::destroy(1);
        $this->assertTrue($result);

        $count = Db::table('test_model')->where('id', 1)->count();
        $this->assertEquals(0, $count);
    }

    public function testChangeDetection()
    {
        $model = new class extends Model
        {
            protected $table              = 'test_model';
            protected $autoWriteTimestamp = true;
        };

        // 测试新建模型时的变更检测
        $data  = ['name' => 'test5', 'status' => 1];
        $model = new $model($data);

        // 测试更新模型时的变更检测
        $model->name = 'updated';
        $this->assertTrue($model->isChange('name'));
        $this->assertFalse($model->isChange('status'));
        $this->assertEquals(['name' => 'updated'], $model->getChangedData());

        // 测试多字段变更
        $model->status = 0;
        $this->assertTrue($model->isChange('name'));
        $this->assertTrue($model->isChange('status'));
        $this->assertEquals(['name' => 'updated', 'status' => 0], $model->getChangedData());
        $model->save();

        // 测试设置相同的值不会触发变更
        $model->name = 'updated';
        $this->assertFalse($model->isChange('name'));
        $this->assertEquals([], $model->getChangedData());
    }

    public function testSave()
    {
        $model = new class extends Model
        {
            protected $table              = 'test_model';
            protected $autoWriteTimestamp = true;
        };

        $data   = ['name' => 'test5', 'status' => 1];
        $model  = new $model($data);
        $result = $model->save();

        $this->assertTrue($result);
        $this->assertNotEmpty($model->id);
        $this->assertEquals($data['name'], $model->name);
        $this->assertEquals($data['status'], $model->status);
        $this->assertNotEmpty($model->create_time);
    }

    public function testGlobalScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = new class extends Model
        {
            protected $table       = 'test_model';
            protected $globalScope = ['status'];

            public function scopeStatus($query)
            {
                return $query->where('status', 1);
            }
        };

        $result = $model::select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertEquals(1, $item->status);
        }
    }

    public function testLocalScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = new class extends Model
        {
            protected $table = 'test_model';

            public function scopeActive($query)
            {
                return $query->where('status', 1);
            }

            public function scopeNameLike($query, $name)
            {
                return $query->where('name', 'like', "%{$name}%");
            }
        };

        // 测试基本查询范围
        $result = $model::active()->select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertEquals(1, $item->status);
        }

        // 测试带参数的查询范围
        $result = $model::nameLike('test1')->select();
        $this->assertCount(1, $result);
        $this->assertEquals('test1', $result[0]->name);

        // 测试组合查询范围
        $result = $model::active()->nameLike('test')->select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertEquals(1, $item->status);
            $this->assertStringContainsString('test', $item->name);
        }
    }

    public function testRemoveScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = new class extends Model
        {
            protected $table       = 'test_model';
            protected $globalScope = ['status'];

            public function scopeStatus($query)
            {
                return $query->where('status', 1);
            }
        };

        // 测试移除全局查询范围
        $result = $model::withoutGlobalScope()->select();
        $this->assertCount(3, $result);

        // 测试指定移除某个全局查询范围
        $result = $model::withoutGlobalScope(['status'])->select();
        $this->assertCount(3, $result);

        // 测试移除多个全局查询范围
        $result = $model::withoutGlobalScope(['status', 'other'])->select();
        $this->assertCount(3, $result);
    }
}
