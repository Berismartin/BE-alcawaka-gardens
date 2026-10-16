<?php

namespace App\Domains\Reservations\Reservation\Enums;

enum ChargeCategory: string
{
    case Room = 'room';
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case Laundry = 'laundry';
    case Facility = 'facility';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room',
            self::Restaurant => 'Restaurant',
            self::Bar => 'Bar',
            self::Laundry => 'Laundry',
            self::Facility => 'Facility',
            self::Other => 'Other',
        };
    }
}
