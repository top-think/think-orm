<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\concern\SoftDelete;
use think\model\relation\BelongsTo;

/**
 * 用户资料模型
 */
class ProfileModel extends Model
{
    use SoftDelete;

    protected $table = 'test_profile';

    /**
     * 用户
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class);
    }
}
