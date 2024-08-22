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
              `uid` int NOT NULL,
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
        $user = UserModel::with('profile')->find($userID);
        $this->assertEquals(
            [$userID, $email, $nickname, $nickname],
            [$user->id, $user->email, $user->new_name, $user->call_name]
        );

        // 动态绑定
        $user = UserModel::find($userID)
            ->bindAttr(
                'profile',
                ['email', 'nick_name' => 'nickname', 'true_name' => fn ($model) =>$model?->getAttr('nickname')]
            );
        $this->assertEquals(
            [$userID, $email, $nickname, $nickname],
            [$user->id, $user->email, $user->nick_name, $user->true_name]
        );
    }

}
