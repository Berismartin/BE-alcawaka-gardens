<?php

namespace App\Domains\Facilities\Facility\Enums;

enum FacilityAccess: string
{
    case FreeForGuests = 'free_for_guests';
    case Paid = 'paid';
    case Reservation = 'reservation';

    public function requiresPrice(): bool
    {
        return $this !== self::FreeForGuests;
    }
}
