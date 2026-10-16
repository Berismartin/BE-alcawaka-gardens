<?php

namespace App\Domains\Users\User\Enums;

enum UserRole: string
{
    case Guest = 'guest';
    case FrontDesk = 'front_desk';
    case Housekeeping = 'housekeeping';
    case Manager = 'manager';
    case Owner = 'owner';

    public function isStaff(): bool
    {
        return $this !== self::Guest;
    }
}
