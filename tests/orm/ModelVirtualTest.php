<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\VirtualModel;
use think\model\Virtual;

/**
 * 虚拟模型测试
 */
class ModelVirtualTest extends TestCase
{
    public function testVirtualModelBasic()
    {
        // 测试数据操作
        $model = new VirtualModel();
        $data  = ['name' => 'test', 'age' => 18];
        $this->assertTrue($model->save($data));
        $this->assertEquals($data, $model->getData());

        // 测试更新数据
        $updateData = ['age' => 20];
        $this->assertTrue($model->save($updateData));
        $this->assertEquals(20, $model->getData('age'));

        // 测试删除数据
        $this->assertTrue($model->delete());
        $this->assertEmpty($model->getData());
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
