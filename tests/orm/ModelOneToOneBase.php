<?php
declare(strict_types=1);

namespace tests\orm;

use tests\stubs\ProfileModel;
use tests\stubs\UserModel;
use tests\TestCaseBase;
use think\Model;

/**
 * 模型一对一关联
 */
abstract class ModelOneToOneBase extends TestCaseBase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // todo 需要一个重置能力更安全
        Model::maker(function (Model $model) {
            $model->setConnection(static::$connectName);
            var_dump('maker:' . __FUNCTION__ . '-' . $model::class . '-' . spl_object_id($model));
        });
    }

    public function setUp(): void
    {
        parent::setUp();

        // 每个测试执行前重置测试数据
        $this->db->execute('TRUNCATE TABLE orm_test_user;');
        $this->db->execute('TRUNCATE TABLE orm_test_profile;');
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
        $profile = new ProfileModel(['email' => $email, 'nickname' => $nickname]);
        $user->profile = $profile;
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
