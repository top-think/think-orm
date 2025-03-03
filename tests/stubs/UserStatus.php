<?php
declare(strict_types=1);

namespace app\model;
use think\model\contract\EnumTransform;

enum UserStatus: string implements EnumTransform
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';

    public function value()
    {
        return match($this) {
            UserStatus::Active   => 'active',
            UserStatus::Inactive => 'inactive',
            UserStatus::Pending  => 'pending',
        };
    }
}