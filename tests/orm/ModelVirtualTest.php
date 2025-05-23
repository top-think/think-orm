<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\db\exception\DbException;
use think\model\Virtual;

/**
 * 虚拟模型
 */
class VirtualModel extends Virtual
{
}

/**
 * 虚拟模型测试
 */
class ModelVirtualTest extends TestCase
{
    public function testVirtualModelBasic()
    {
        // 测试数据操作
        $model = new VirtualModel();
        $model->name = 'test';
        $model->age  = 18;
        $this->assertEquals('test', $model->name);
        $this->assertEquals(18, $model->age);

        // 测试更新数据
        $model->age = 20;
        $this->assertEquals(20, $model->getData('age'));

        $this->expectException(DbException::class);
        $this->expectExceptionMessage("virtual model not support db query");
        $model->save();
    }

    public function testVirtualDelete()
    {
        $model = new VirtualModel(['name' => 'test', 'age' => 18]);
        $this->assertEquals('test', $model->name);
        $this->assertEquals(18, $model->age);

        $this->expectException(DbException::class);
        $this->expectExceptionMessage("virtual model not support db query");
        $model->delete();
    }

    public function testVirtualModelCreate()
    {
        // 测试create方法创建实例
        $data = ['name' => 'virtual', 'age' => 25];
        $model = VirtualModel::create($data);

        // 验证创建的实例
        $this->assertInstanceOf(Virtual::class, $model);
        $this->assertEquals($data, $model->getData());
        $this->assertEquals('virtual', $model->getData('name'));
        $this->assertEquals(25, $model->getData('age'));
    }
}
