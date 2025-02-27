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
            'DROP TABLE IF EXISTS `user`;',
            'CREATE TABLE `test_user_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `account`;',
            'CREATE TABLE `test_account_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `account` varchar(255) NOT NULL DEFAULT "",
                `create_time` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_profile_id` (`profile_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;',
            'DROP TABLE IF EXISTS `profile`;',
            'CREATE TABLE `test_profile_through` (
                `id` int NOT NULL AUTO_INCREMENT,
                `account_id` int NOT NULL,
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
        Db::execute('TRUNCATE TABLE `user`;');
        Db::execute('TRUNCATE TABLE `account`;');
        Db::execute('TRUNCATE TABLE `profile`;');

        // 创建测试数据
        $user1 = User::create([
            'name' => 'user1'
        ]);

        $user2 = User::create([
            'name' => 'user2'
        ]);

        $account1 = Account::create([
            'user_id' => $user1->id,
            'account' => 'account1'
        ]);

        $account2 = Account::create([
            'user_id' => $user2->id,
            'account' => 'account2'
        ]);

        $profile1 = Profile::create([
            'account_id' => $account1->id,
            'email' => 'user1@example.com',
            'nickname' => 'nickname1'
        ]);

        $profile2 = Profile::create([
            'account_id' => $account2->id,
            'email' => 'user2@example.com',
            'nickname' => 'nickname2'
        ]);
    }

    public function testHasOneThrough()
    {
        // 测试关联获取
        $user = User::find(1);
        $this->assertNotNull($user);
        
        $profile = $user->profile;
        $this->assertNotNull($profile);
        $this->assertEquals('user1@example.com', $profile->email);
        $this->assertEquals('nickname1', $profile->nickname);

        // 测试预加载
        $user = User::with(['profile'])->find(1);
        $this->assertEquals('user1@example.com', $user->profile->email);

        // 测试关联查询条件
        $user = User::hasWhere('profile', ['nickname' => 'nickname1'])->find();
        $this->assertNotNull($user);
        $this->assertEquals('user1', $user->name);

        // 测试关联统计
        $user = User::withCount('profile')->find(1);
        $this->assertEquals(1, $user->profile_count);
    }
}

class User extends Model
{
    protected $autoWriteTimestamp = true;

    public function profile()
    {
        return $this->hasOneThrough(
            Profile::class,
            Account::class,
            'user_id',
            'account_id'
        );
    }
}

class Account extends Model
{
    protected $autoWriteTimestamp = true;
}

class Profile extends Model
{
    protected $autoWriteTimestamp = true;
}