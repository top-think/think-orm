<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\relation\HasOne;

/**
 * 用户模型
 */
class UserModel extends Model
{
    protected $table = 'test_user';
    protected $pk = 'id';

    /**
     * 用户资料
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class, 'uid')
            ->bind([
                'email',
                'new_name'	=> 'nickname',
                'call_name' => fn ($model) =>$model?->getAttr('nickname')
            ]);
    }
}
