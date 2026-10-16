<?php

namespace App\Domains\Reservations\Reservation\Enums;

enum ReservationSource: string
{
    case Website = 'website';
    case FrontDesk = 'front_desk';
    case Phone = 'phone';
    case Ota = 'ota';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::FrontDesk => 'Front desk',
            self::Phone => 'Phone',
            self::Ota => 'OTA channels',
        };
    }
}
