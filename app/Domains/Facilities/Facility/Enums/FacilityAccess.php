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

    /** Online price: a day pass is per person; a reservation (event space, meeting room) is one price. */
    public function onlineTotal(int $price, int $partySize): int
    {
        return $this === self::Paid ? $price * $partySize : $price;
    }
}
