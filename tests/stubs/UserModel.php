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
    protected $autoWriteTimestamp = false;

    /**
     * 用户资料
     * @return HasOne
     */
    public function profile(): HasOne
    {
        return $this->hasOne(ProfileModel::class)
            ->bind([
                'email',
                'new_name'	=> 'nickname'
            ]);
    }
}
