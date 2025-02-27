<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use tests\stubs\ProfileModel;
use tests\stubs\UserModel;
use think\facade\Db;

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
              `account` varchar(255) NOT NULL DEFAULT "",
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
        $email = mt_rand(10000, 99999) . '@thinkphp.cn';
        $nickname = 'u' . mt_rand(10000, 99999);

        $user = new UserModel();
        $user->account = 'thinkphp';
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
        $user = new UserModel();
        $user->account = 'thinkphp';
        $user->save();

        $profile = new ProfileModel();
        $profile->email = 'test@thinkphp.cn';
        $profile->nickname = 'test';
        $profile->user_id = $user->id;
        $profile->save();

        // 测试hasOne关联
        $user = UserModel::find($user->id);
        $this->assertNotNull($user->profile);
        $this->assertEquals('test@thinkphp.cn', $user->profile->email);

        // 测试belongsTo关联
        $profile = ProfileModel::find($profile->id);
        $this->assertNotNull($profile->user);
        $this->assertEquals('thinkphp', $profile->user->account);
    }

    /**
     * 测试预加载查询
     */
    public function testEagerLoading()
    {
        // 创建测试数据
        $user1 = new UserModel(['account' => 'user1']);
        $user1->save();
        $user2 = new UserModel(['account' => 'user2']);
        $user2->save();

        $profile1 = new ProfileModel([
            'user_id' => $user1->id,
            'email' => 'user1@thinkphp.cn',
            'nickname' => 'nickname1'
        ]);
        $profile1->save();

        $profile2 = new ProfileModel([
            'user_id' => $user2->id,
            'email' => 'user2@thinkphp.cn',
            'nickname' => 'nickname2'
        ]);
        $profile2->save();

        // 测试with预加载
        $users = UserModel::with(['profile'])->select();
        $this->assertCount(2, $users);
        $this->assertEquals('user1@thinkphp.cn', $users[0]->profile->email);
        $this->assertEquals('user2@thinkphp.cn', $users[1]->profile->email);

        // 测试预加载条件
        $users = UserModel::with(['profile' => function($query) {
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
        $user = new UserModel();
        $user->account = 'newuser';
        $user->profile = new ProfileModel(['email' => 'new@thinkphp.cn', 'nickname' => 'newnick']);
        $user->together(['profile'])->save();

        $this->assertNotNull($user->profile);
        $this->assertEquals('new@thinkphp.cn', $user->profile->email);

        // 测试关联更新
        $user = UserModel::with(['profile'])->where('account', 'newuser')->find();
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
        $user = new UserModel();
        $user->account = 'deletetest';
        $user->profile = new ProfileModel(['email' => 'delete@thinkphp.cn', 'nickname' => 'deletenick']);
        $user->together(['profile'])->save();

        $profileId = $user->profile->id;
        $user->delete();

        // 验证关联数据是否被删除
        $this->assertNull(ProfileModel::find($profileId));
    }
}
