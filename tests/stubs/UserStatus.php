<?php
declare(strict_types=1);)

namespace tests\stubs;

enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
}