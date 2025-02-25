<?php
declare(strict_types=1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelHasOneThroughTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $sqlList = [
            'DROP TABLE IF EXISTS `test_user_through`;',
            'CREATE TABLE `test_user_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_account_through`;',
            'CREATE TABLE `test_account_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `profile_id` int NOT NULL,
                `account` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_profile_id` (`profile_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `test_profile_through`;',
            'CREATE TABLE `test_profile_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL DEFAULT "",
                `nickname` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
        ];
        foreach ($sqlList as $sql) {
            Db::execute($sql);
        }
    }

    protected function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_user_through`;');
        Db::execute('TRUNCATE TABLE `test_account_through`;');
        Db::execute('TRUNCATE TABLE `test_profile_through`;');

        // 创建测试数据
        $user1 = UserThroughModel::create([
            'name' => 'user1'
        ]);

        $user2 = UserThroughModel::create([
            'name' => 'user2'
        ]);

        $profile1 = ProfileThroughModel::create([
            'email' => 'user1@example.com',
            'nickname' => 'nickname1'
        ]);

        $profile2 = ProfileThroughModel::create([
            'email' => 'user2@example.com',
            'nickname' => 'nickname2'
        ]);

        AccountThroughModel::create([
            'user_id' => $user1->id,
            'profile_id' => $profile1->id,
            'account' => 'account1'
        ]);

        AccountThroughModel::create([
            'user_id' => $user2->id,
            'profile_id' => $profile2->id,
            'account' => 'account2'
        ]);
    }

    public function testHasOneThrough()
    {
        // 测试关联获取
        $user = UserThroughModel::find(1);
        $this->assertNotNull($user);
        
        $profile = $user->profile;
        $this->assertNotNull($profile);
        $this->assertEquals('user1@example.com', $profile->email);
        $this->assertEquals('nickname1', $profile->nickname);

        // 测试预加载
        $user = UserThroughModel::with(['profile'])->find(1);
        $this->assertTrue($user->isRelationLoaded('profile'));
        $this->assertEquals('user1@example.com', $user->profile->email);

        // 测试关联查询条件
        $user = UserThroughModel::hasWhere('profile', ['nickname' => 'nickname1'])->find();
        $this->assertNotNull($user);
        $this->assertEquals('user1', $user->name);

        // 测试关联统计
        $user = UserThroughModel::withCount('profile')->find(1);
        $this->assertEquals(1, $user->profile_count);
    }
}

class UserThroughModel extends Model
{
    protected $table = 'test_user_through';
    protected $autoWriteTimestamp = true;

    public function profile()
    {
        return $this->hasOneThrough(
            ProfileThroughModel::class,
            AccountThroughModel::class,
            'user_id',
            'id',
            'id',
            'profile_id'
        );
    }
}

class AccountThroughModel extends Model
{
    protected $table = 'test_account_through';
    protected $autoWriteTimestamp = true;
}

class ProfileThroughModel extends Model
{
    protected $table = 'test_profile_through';
    protected $autoWriteTimestamp = true;
}