<?php

namespace App\Domains\Users\User\Enums;

/**
 * Staff roles and what each may do (deck slide 16).
 * Abilities are "<area>.<view|manage>"; manage implies view.
 */
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

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Guest => [],
            self::FrontDesk => [
                'dashboard.view', 'reservations.manage', 'guests.manage', 'payments.record',
                'rooms.view', 'housekeeping.view', 'facilities.view', 'media.upload',
            ],
            self::Housekeeping => ['housekeeping.manage', 'rooms.view'],
            self::Manager => ['*'],
            self::Owner => [
                'dashboard.view', 'reports.view', 'reservations.view', 'payments.view',
                'rooms.view', 'facilities.view', 'audit.view',
            ],
        };
    }

    public function can(string $ability): bool
    {
        $granted = $this->permissions();
        if (in_array('*', $granted, true) || in_array($ability, $granted, true)) {
            return true;
        }

        // "x.manage" includes "x.view".
        return str_ends_with($ability, '.view')
            && in_array(substr($ability, 0, -strlen('.view')).'.manage', $granted, true);
    }
}
