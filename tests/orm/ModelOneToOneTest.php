<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;
use think\model\concern\SoftDelete;
use think\model\relation\BelongsTo;
use think\model\relation\HasOne;

/**
 * 模型一对一关联
 */
class ModelOneToOneTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_user`;',
            'CREATE TABLE `test_user`  (
              `id` int NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL DEFAULT "",
              PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_profile`;',
            'CREATE TABLE `test_profile` (
              `id` int NOT NULL AUTO_INCREMENT,
              `user_id` int NOT NULL,
              `email` varchar(255) NOT NULL DEFAULT "",
              `nickname` varchar(255) NOT NULL DEFAULT "",
              `update_time` datetime NOT NULL,
              `delete_time` datetime DEFAULT NULL,
              `create_time` datetime NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user`;');
        Db::execute('TRUNCATE TABLE `test_profile`;');
    }

    /**
     * 绑定属性
     */
    public function testBindAttr()
    {
        $email    = mt_rand(10000, 99999) . '@thinkphp.cn';
        $nickname = 'u' . mt_rand(10000, 99999);

        $user          = new UserModel();
        $user->name    = 'thinkphp';
        $user->profile = new ProfileModel(['email' => $email, 'nickname' => $nickname]);
        $user->together(['profile'])->save();

        $userID = $user->id;

        // 预载入时绑定
        $user = UserModel::with(['profile'])->find($userID);
        $this->assertEquals(
            [$userID, $email, $nickname],
            [$user->id, $user->email, $user->new_name]
        );

        // 动态绑定
        $user = UserModel::find($userID)
            ->bindAttr(
                'profile',
                ['email', 'nick_name' => 'nickname']
            );
        $this->assertEquals(
            [$userID, $email, $nickname],
            [$user->id, $user->email, $user->nick_name]
        );
    }
    /**
     * 测试基础关联查询
     */
    public function testBasicRelation()
    {
        $user       = new UserModel();
        $user->name = 'thinkphp';
        $user->save();

        $profile           = new ProfileModel();
        $profile->email    = 'test@thinkphp.cn';
        $profile->nickname = 'test';
        $profile->user_id  = $user->id;
        $profile->save();

        // 测试hasOne关联
        $user = UserModel::find($user->id);
        $this->assertNotNull($user->profile);
        $this->assertEquals('test@thinkphp.cn', $user->profile->email);

        // 测试belongsTo关联
        $profile = ProfileModel::find($profile->id);
        $this->assertNotNull($profile->user);
        $this->assertEquals('thinkphp', $profile->user->name);
    }

    /**
     * 测试预加载查询
     */
    public function testEagerLoading()
    {
        // 创建测试数据
        $user1 = new UserModel();
        $user1->save(['name' => 'user1']);
        $user2 = new UserModel();
        $user2->save(['name' => 'user2']);

        $profile1 = new ProfileModel([
            'user_id'  => $user1->id,
            'email'    => 'user1@thinkphp.cn',
            'nickname' => 'nickname1',
        ]);
        $profile1->save();

        $profile2 = new ProfileModel([
            'user_id'  => $user2->id,
            'email'    => 'user2@thinkphp.cn',
            'nickname' => 'nickname2',
        ]);
        $profile2->save();

        // 测试with预加载
        $users = UserModel::with(['profile'])->select();
        $this->assertCount(2, $users);
        $this->assertEquals('user1@thinkphp.cn', $users[0]->profile->email);
        $this->assertEquals('user2@thinkphp.cn', $users[1]->profile->email);

        // 测试预加载条件
        $users = UserModel::with(['profile' => function ($query) {
            $query->where('nickname', 'nickname1');
        }])->select();
        $this->assertNotNull($users[0]->profile);
        $this->assertNull($users[1]->profile);
    }

    /**
     * 测试关联数据的新增和更新
     */
    public function testRelationSave()
    {
        // 测试关联新增
        $user          = new UserModel();
        $user->name    = 'newuser';
        $user->profile = new ProfileModel(['email' => 'new@thinkphp.cn', 'nickname' => 'newnick']);
        $user->together(['profile'])->save();

        $this->assertNotNull($user->profile);
        $this->assertEquals('new@thinkphp.cn', $user->profile->email);

        // 测试关联更新
        $user                 = UserModel::with(['profile'])->where('name', 'newuser')->find();
        $user->profile->email = 'updated@thinkphp.cn';
        $user->together(['profile'])->save();

        $profile = ProfileModel::find($user->profile->id);
        $this->assertEquals('updated@thinkphp.cn', $profile->email);
    }

    /**
     * 测试关联删除
     */
    public function testRelationDelete()
    {
        $user          = new UserModel();
        $user->name    = 'deletetest';
        $user->profile = new ProfileModel(['email' => 'delete@thinkphp.cn', 'nickname' => 'deletenick']);
        $user->together(['profile'])->save();

        $profileId = $user->profile->id;
        $user->delete();

        // 验证关联数据是否被删除
        $this->assertNull(ProfileModel::find($profileId));
    }

    /**
     * 测试关联统计
     */
    public function testRelationCount()
    {
        // 创建测试数据
        $user1 = new UserModel();
        $user1->save(['name' => 'user1']);
        $user2 = new UserModel();
        $user2->save(['name' => 'user2']);

        $profile1 = new ProfileModel([
            'user_id'  => $user1->id,
            'email'    => 'user1@thinkphp.cn',
            'nickname' => 'nickname1',
        ]);
        $profile1->save();

        $profile2 = new ProfileModel([
            'user_id'  => $user2->id,
            'email'    => 'user2@thinkphp.cn',
            'nickname' => 'nickname2',
        ]);
        $profile2->save();

        // 测试关联统计
        $userCount = UserModel::withCount('profile')->find($user1->id);
        $this->assertEquals(1, $userCount->profile_count);

        $userCount = UserModel::withCount('profile')->find($user2->id);
        $this->assertEquals(1, $userCount->profile_count);
    }

    /**
     * 测试 has 查询
     */
    public function testHasQuery()
    {
        // 创建测试数据
        $user1 = new UserModel();
        $user1->save(['name' => 'user1']);
        $user2 = new UserModel();
        $user2->save(['name' => 'user2']);
        $user3 = new UserModel();
        $user3->save(['name' => 'user3']);

        // 只给 user1 和 user2 创建 profile
        $profile1 = new ProfileModel([
            'user_id'  => $user1->id,
            'email'    => 'user1@thinkphp.cn',
            'nickname' => 'nickname1',
        ]);
        $profile1->save();

        $profile2 = new ProfileModel([
            'user_id'  => $user2->id,
            'email'    => 'user2@thinkphp.cn',
            'nickname' => 'nickname2',
        ]);
        $profile2->save();

        // 测试基础 has 查询
        $users = UserModel::has('profile')->select();
        $this->assertCount(2, $users);
        $this->assertEquals(['user1', 'user2'], $users->column('name'));

        // 测试 hasWhere 查询
        $users = UserModel::hasWhere('profile', ['nickname' => 'nickname1'])->select();
        $this->assertCount(1, $users);
        $this->assertEquals('user1', $users[0]->name);

        // 测试 hasWhere 闭包查询
        $users = UserModel::hasWhere('profile', function ($query) {
            $query->where('email', 'like', '%@thinkphp.cn')
                  ->where('nickname', 'nickname1');
        })->select();
        $this->assertCount(1, $users);
        $this->assertEquals('user1', $users[0]->name);

        // 测试 hasWhere 闭包查询与 OR 条件
        $users = UserModel::hasWhere('profile', function ($query) {
            $query->where('nickname', 'nickname1')
                  ->whereOr('nickname', 'nickname2');
        })->select();
        $this->assertCount(2, $users);
        $this->assertEquals(['user1', 'user2'], $users->column('name'));

        // 测试 has 与 where 组合查询
        $users = UserModel::has('profile')
            ->where('name', 'like', 'user%')
            ->select();
        $this->assertCount(2, $users);

        // 测试基础 hasNot 查询
        $users = UserModel::hasNot('profile')->select();
        $this->assertCount(1, $users);
        $this->assertEquals(['user3'], $users->column('name'));

        // 测试软删除数据
        $profile2->delete();
        $users = UserModel::has('profile')->select();
        $this->assertCount(1, $users);
    }

    /**
     * 测试追加关联属性
     */
    public function testRelationAppend()
    {
        $user       = new UserModel();
        $user->name = 'thinkphp';
        $user->save();

        $profile           = new ProfileModel();
        $profile->email    = 'test@thinkphp.cn';
        $profile->nickname = 'test';
        $profile->user_id  = $user->id;
        $profile->save();

        // 测试hasOne关联
        $user = UserModel::where('id', $user->id)->append(['profile' => ['user_id' => 'uid']])->find()->toArray();
        $this->assertEquals($profile->user_id, $user['uid']);
    }
}

/**
 * 用户模型
 */
class UserModel extends Model
{
    protected $name               = 'user';
    protected $autoWriteTimestamp = false;

    /**
     * 用户资料
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class, 'user_id')
            ->bind([
                'email',
                'new_name' => 'nickname',
            ]);
    }
}

/**
 * 用户资料模型
 */
class ProfileModel extends Model
{
    use SoftDelete;
    protected $name               = 'profile';
    protected $autoWriteTimestamp = true;

    /**
     * 用户模型
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }    
}
