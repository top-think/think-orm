<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\Entity;
use think\facade\Db;
use think\Model;

// 用于测试关联关系的 Profile 实体
class TestProfileEntity extends Entity
{
    protected function getOptions(): array
    {
        return [
            'modelClass' => TestProfileModel::class,
        ];
    }
}

class TestProfileModel extends Model
{
    protected $name = 'entity_profile';
}

// 测试用的实体类
class TestEntity extends Entity
{
    protected function getOptions(): array
    {
        return [
            'modelClass' => TestEntityModel::class,
        ];
    }
}

// 测试用的模型类
class TestEntityModel extends Model
{
    protected $name = 'entity';

    public function profile()
    {
        return $this->hasOne(TestProfileModel::class);
    }

    // 自定义获取器
    public function getStatusTextAttr($value, $data)
    {
        $status = [
            0 => 'disabled',
            1 => 'enabled',
        ];
        return $status[$data['status']] ?? 'unknown';
    }
}

class EntityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Db::execute('DROP TABLE IF EXISTS `test_entity`');
        Db::execute('DROP TABLE IF EXISTS `test_entity_profile`');

        Db::execute(<<<SQL
CREATE TABLE `test_entity` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `age` int(11) NOT NULL DEFAULT '0',
    `status` tinyint(1) NOT NULL DEFAULT '0',
    `create_time` datetime DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );

        Db::execute(<<<SQL
CREATE TABLE `test_entity_profile` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `entity_id` int(11) NOT NULL,
    `email` varchar(255) NOT NULL,
    `address` varchar(255) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `entity_id` (`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function testEntityConstruct()
    {
        // 测试无模型构造
        $entity = new TestEntity();
        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertInstanceOf(Model::class, $entity->model());

        // 测试带模型构造
        $model  = new TestEntityModel();
        $entity = new TestEntity($model);
        $this->assertInstanceOf(Entity::class, $entity);
        $this->assertInstanceOf(Model::class, $entity->model());
        $this->assertSame($model, $entity->model());
    }

    public function testEntityAttributes()
    {
        $entity = new TestEntity();

        // 测试属性设置和获取
        $entity->name   = 'test';
        $entity->age    = '25'; // 应该被转换为整数
        $entity->status = 1;

        $this->assertEquals('test', $entity->name);
        $this->assertEquals(25, $entity->age);
        $this->assertEquals(1, $entity->status);
        $this->assertEquals('enabled', $entity->status_text);
    }

    public function testEntityArrayAccess()
    {
        $entity = new TestEntity();

        // 测试数组式访问
        $entity['name'] = 'test';
        $this->assertTrue(isset($entity['name']));
        $this->assertEquals('test', $entity['name']);

        // 测试数组式删除
        unset($entity['name']);
        $this->assertFalse(isset($entity['name']));
    }

    public function testEntityToArray()
    {
        $entity         = new TestEntity();
        $entity->name   = 'test';
        $entity->age    = 25;
        $entity->status = 1;

        $array = $entity->append(['status_text'])->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('test', $array['name']);
        $this->assertEquals(25, $array['age']);
        $this->assertEquals(1, $array['status']);
        $this->assertEquals('enabled', $array['status_text']);
    }

    public function testEntityJson()
    {
        $entity         = new TestEntity();
        $entity->name   = 'test';
        $entity->age    = 25;
        $entity->status = 1;
        $entity->append(['status_text']);

        // 测试 JSON 序列化
        $json = json_encode($entity);
        $this->assertJson($json);

        $data = json_decode($json, true);
        $this->assertEquals('test', $data['name']);
        $this->assertEquals(25, $data['age']);
        $this->assertEquals(1, $data['status']);
        $this->assertEquals('enabled', $data['status_text']);
    }

    public function testNewInstance()
    {
        $entity       = new TestEntity();
        $entity->name = 'test';

        $model     = new TestEntityModel(['name' => 'new instance']);
        $newEntity = $entity->newInstance($model);

        $this->assertInstanceOf(TestEntity::class, $newEntity);
        $this->assertNotSame($entity, $newEntity);
        $this->assertEquals('new instance', $newEntity->name);
    }

    public function testEntityRelation()
    {
        // 创建实体和关联数据
        $entity         = new TestEntity();
        $entity->name   = 'test';
        $entity->age    = 25;
        $entity->status = 1;
        $entity->save();

        $profile = new TestProfileModel([
            'entity_id' => $entity->id,
            'email'     => 'test@example.com',
            'address'   => 'Test Address',
        ]);
        $profile->save();

        // 测试关联查询
        $entity = TestEntity::with(['profile'])->find($entity->id);
        $this->assertNotNull($entity->profile);
        $this->assertEquals('test@example.com', $entity->profile->email);

        // 测试关联数据转数组
        $array = $entity->toArray();
        $this->assertArrayHasKey('profile', $array);
        $this->assertEquals('test@example.com', $array['profile']['email']);
    }

    public function testEntityWithRelation()
    {
        $entity         = new TestEntity();
        $entity->name   = 'test';
        $entity->age    = 25;
        $entity->status = 1;
        $entity->save();

        // 通过实体访问关联方法
        $profile = $entity->profile();
        $this->assertInstanceOf(\think\model\relation\HasOne::class, $profile);

        // 保存关联数据
        $profile->save([
            'email'   => 'test@example.com',
            'address' => 'Test Address',
        ]);

        // 重新加载数据
        $entity = TestEntity::with(['profile'])->find($entity->id);
        $this->assertEquals('test@example.com', $entity->profile->email);
    }

    public function testEntityWithEmptyRelation()
    {
        $entity       = new TestEntity();
        $entity->name = 'test';
        $entity->save();

        // 测试不存在的关联数据
        $entity = TestEntity::with(['profile'])->find($entity->id);
        $this->assertNull($entity->profile);

        // 测试空关联转数组
        $array = $entity->toArray();
        $this->assertArrayHasKey('profile', $array);
        $this->assertNull($array['profile']);
    }

    public function testEntityDataAccess()
    {
        $entity = new TestEntity();

        // 测试数据存取
        $entity->name = 'test';
        $this->assertEquals('test', $entity->name);

        // 测试批量赋值
        $data = [
            'name'   => 'batch test',
            'age'    => 30,
            'status' => 1,
        ];
        foreach ($data as $key => $value) {
            $entity->set($key, $value);
        }

        $this->assertEquals($data['name'], $entity->name);
        $this->assertEquals($data['age'], $entity->age);
        $this->assertEquals($data['status'], $entity->status);

                                               // 测试原始数据获取
        $entity->age = '35';                   // 字符串类型
        $this->assertEquals(35, $entity->age); // 应该被转换为整数
    }

    public function testEntityDataChanges()
    {
        $entity = new TestEntity();

        // 设置初始数据
        $entity->name = 'original';
        $entity->age  = 25;
        $entity->save();

        // 修改数据
        $entity->name = 'changed';
        $entity->age  = 30;

        // 检查变更数据
        $changes = $entity->getChangedData();
        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayHasKey('age', $changes);
        $this->assertEquals('changed', $changes['name']);
        $this->assertEquals(30, $changes['age']);
    }

    public function testEntityClone()
    {
        // 创建原始实体并设置数据
        $entity         = new TestEntity();
        $entity->name   = 'original';
        $entity->age    = 25;
        $entity->status = 1;
        $entity->save();

        // 保存关联数据
        $entity->profile()->save([
            'email'   => 'original@example.com',
            'address' => 'Original Address',
        ]);

        // 克隆实体
        $clonedEntity = $entity->clone();

        // 验证基本属性克隆
        $this->assertEquals($entity->name, $clonedEntity->name);
        $this->assertEquals($entity->age, $clonedEntity->age);
        $this->assertEquals($entity->status, $clonedEntity->status);
        $this->assertEquals($entity->status_text, $clonedEntity->status_text);

        // 验证克隆后的数据独立性
        $clonedEntity->name = 'cloned';
        $clonedEntity->age  = 30;
        $this->assertEquals('cloned', $entity->name);
        $this->assertEquals(30, $entity->age);
        $this->assertEquals('cloned', $clonedEntity->name);
        $this->assertEquals(30, $clonedEntity->age);

        // 验证关联数据克隆
        $entity       = TestEntity::with(['profile'])->find($entity->id);
        $clonedEntity = $entity->clone();

        $this->assertEquals($entity->profile->email, $clonedEntity->profile->email);
        $this->assertEquals($entity->profile->address, $clonedEntity->profile->address);
    }
}
