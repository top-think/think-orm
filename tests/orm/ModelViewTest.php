<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;
use think\model\View;

class UserProfileModel extends Model
{
    protected $name = 'user_profile';
}

class UserViewModel extends View
{
    public $id;
    public $nickname;
    public $test_name;
    public $user_age;
    public $email;
    public $address;

    protected function getOptions(): array
    {
        return [
            'modelClass'  => TestUserModel::class,
            'autoMapping' => ['profile'],
            'viewMapping' => [
                'nickname' => 'name',
                'user_age' => 'age',
                'email'    => 'profile->email',
                'address'  => 'profile->address',
            ],
        ];
    }

    public function setReadonly(bool $readonly)
    {
        return $this->setOption('readonly', $readonly);
    }
}

class TestUserModel extends Model
{
    protected $name = 'user_view';

    public function profile()
    {
        return $this->hasOne(UserProfileModel::class, 'user_id');
    }

    public function getTestNameAttr($value, $data)
    {
        return 'test_' . $data['name'];
    }
}

class ModelViewTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_user_view`;');
        Db::execute('DROP TABLE IF EXISTS `test_user_profile`;');

        Db::execute(
            <<<'SQL'
CREATE TABLE `test_user_view` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` varchar(32) NOT NULL,
    `age` int(11) NOT NULL DEFAULT '0',
    `status` tinyint(4) NOT NULL DEFAULT '0',
    `create_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );

        Db::execute(
            <<<'SQL'
CREATE TABLE `test_user_profile` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `user_id` int(10) UNSIGNED NOT NULL,
    `email` varchar(255) NOT NULL,
    `address` varchar(255) NOT NULL,
    `create_time` datetime DEFAULT NULL,
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user_view`;');
        Db::execute('TRUNCATE TABLE `test_user_profile`;');
    }

    public function testViewModelBasic()
    {
        // 创建测试数据
        $model = TestUserModel::create([
            'name'   => 'test1',
            'age'    => 25,
            'status' => 1,
        ]);

        // 使用视图模型
        $viewModel = UserViewModel::find($model->id);

        // 测试属性映射
        $this->assertEquals('test1', $viewModel->nickname);
        $this->assertEquals('test_test1', $viewModel->test_name);
        $this->assertEquals(25, $viewModel->user_age);
        $this->assertNull($viewModel->email);
        $this->assertNull($viewModel->address);

        // 测试原始属性访问
        $this->assertEquals(1, $viewModel->status);

        // 测试toArray转换
        $array = $viewModel->toArray();
        $this->assertIsArray($array);
        $this->assertEquals('test1', $array['nickname']);
        $this->assertEquals(25, $array['user_age']);

        // 测试JSON序列化
        $json = json_encode($viewModel);
        $this->assertJson($json);
        $data = json_decode($json, true);
        $this->assertEquals('test1', $data['nickname']);
    }

    public function testViewModelRelation()
    {
        // 创建测试数据
        $user = TestUserModel::create([
            'name'   => 'test2',
            'age'    => 30,
            'status' => 1,
        ]);

        $viewModel = UserViewModel::find($user->id);

        // 测试数据存在性检查
        $this->assertTrue(isset($viewModel->nickname));
        $this->assertTrue(isset($viewModel->user_age));
        $this->assertFalse(isset($viewModel->not_exist));

        // 测试视图模型写入
        $viewModel->nickname = 'new_name';
        $viewModel->save();
        $viewModel = UserViewModel::find($user->id);
        $this->assertEquals('new_name', $viewModel->nickname);         
    }

    public function testEmptyViewModel()
    {
        $viewModel = new UserViewModel();

        // 测试空模型的属性访问
        $this->assertNull($viewModel->nickname);
        $this->assertNull($viewModel->user_age);

        // 测试空模型的数组转换
        $array = $viewModel->toArray();
        $this->assertIsArray($array);
        $this->assertTrue($viewModel->isEmpty());
    }

    public function testViewModelWithRelation()
    {
        // 创建测试数据
        $user = new UserViewModel;
        $user->nickname = 'test3';
        $user->user_age = 28;
        $user->status   = 1;
        $user->email    = 'test3@example.com';
        $user->address  = 'Test Address';       
        $user->save();

        $user = UserViewModel::create([
            'nickname' => 'test3',
            'user_age' => 28,
            'status'   => 1,
            'email'    => 'test3@example.com',
            'address'  => 'Test Address',            
        ]);        
        // 加载关联数据
        $viewModel = UserViewModel::find($user->id);

        $this->assertEquals('test3', $viewModel->nickname);
        $this->assertEquals('test_test3', $viewModel->test_name);
        $this->assertEquals(28, $viewModel->user_age);
        // 测试关联数据映射
        $this->assertEquals('test3@example.com', $viewModel->email);
        $this->assertEquals('Test Address', $viewModel->address);

        // 测试toArray包含关联数据
        $array = $viewModel->toArray();
        $this->assertEquals('test3@example.com', $array['email']);
        $this->assertEquals('Test Address', $array['address']);

        // 测试JSON序列化包含关联数据
        $json = json_encode($viewModel);
        $data = json_decode($json, true);
        $this->assertEquals('test3@example.com', $data['email']);
        $this->assertEquals('Test Address', $data['address']);

        // 测试关联模型写入
        $viewModel->nickname = 'update_test3';
        $viewModel->email = 'update_test3@example.com';
        $viewModel->address = 'update_Test Address';
        $viewModel->save();

        // 验证关联模型更新
        $updatedModel = UserViewModel::where('nickname', 'update_test3')->where('email', 'update_test3@example.com')->find();
        $this->assertEquals('update_test3', $updatedModel->nickname);
        $this->assertEquals('update_test3@example.com', $updatedModel->email);

        // 批量创建或更新数据
        $dataset = [
            ['nickname' => 'test4',
            'user_age' => 20,
            'status'   => 1,
            'email'    => 'test4@example.com',
            'address'  => 'Address4',],
            ['id'      => 1,
            'nickname' => 'test3',
            'user_age' => 18,
            'status'   => 1,
            'email'    => 'test3@example.com',
            'address'  => 'Test Address',],            
        ];
        $list = UserViewModel::saveAll($dataset);
        foreach ($list as $key => $user) {
            $this->assertEquals($dataset[$key]['nickname'], $user->nickname);
            $this->assertEquals($dataset[$key]['email'], $user->email);
        }
        $viewModel = UserViewModel::find(1);
        $this->assertEquals('test3', $viewModel->nickname);
        $this->assertEquals('test_test3', $viewModel->test_name);
        $this->assertEquals(18, $viewModel->user_age);

        $user = UserViewModel::update(['nickname' => 'new nickname'], ['id' => 1]);
        $this->assertEquals('new nickname', $user->nickname);
        
    }

    public function testViewModelWithoutRelation()
    {
        // 创建测试数据但不加载关联
        $user = TestUserModel::create([
            'name'   => 'test4',
            'age'    => 35,
            'status' => 1,
        ]);

        $viewModel = UserViewModel::find($user->id);

        // 测试未加载关联数据时的属性访问
        $this->assertNull($viewModel->email);
        $this->assertNull($viewModel->address);

        // 测试数组转换
        $array = $viewModel->toArray();
        $this->assertNull($array['email']);
        $this->assertNull($array['address']);
    }

    public function testViewModelClone()
    {
        // 创建测试数据和关联数据
        $user = TestUserModel::create([
            'name'   => 'test5',
            'age'    => 40,
            'status' => 1,
        ]);

        UserProfileModel::create([
            'user_id' => $user->id,
            'email'   => 'test5@example.com',
            'address' => 'Clone Test Address',
        ]);

        // 测试基础克隆（不带关联数据）
        $viewModel   = UserViewModel::find($user->id);
        $clonedModel = $viewModel->clone();

        // 验证基本属性映射
        $this->assertEquals($viewModel->nickname, $clonedModel->nickname);
        $this->assertEquals($viewModel->user_age, $clonedModel->user_age);

        // 验证获取器方法
        $this->assertEquals($viewModel->test_name, $clonedModel->test_name);

        // 验证克隆对象的独立性
        $clonedModel->nickname = 'modified_name';
        $this->assertNotEquals($viewModel->nickname, $clonedModel->nickname);

        // 测试带关联数据的克隆
        $viewModelWithRelation   = UserViewModel::with(['profile'])->find($user->id);
        $clonedModelWithRelation = clone $viewModelWithRelation;

        // 验证关联数据
        $this->assertEquals($viewModelWithRelation->email, $clonedModelWithRelation->email);
        $this->assertEquals($viewModelWithRelation->address, $clonedModelWithRelation->address);

    }
}
