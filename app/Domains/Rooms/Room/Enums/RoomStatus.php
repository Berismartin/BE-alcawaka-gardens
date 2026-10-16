<?php

namespace App\Domains\Rooms\Room\Enums;

enum RoomStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case NeedsCleaning = 'needs_cleaning';
    case UnderMaintenance = 'under_maintenance';
    case OutOfOrder = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Clean and ready',
            self::Occupied => 'Occupied',
            self::NeedsCleaning => 'Needs cleaning',
            self::UnderMaintenance => 'Under maintenance',
            self::OutOfOrder => 'Out of order',
        };
    }

    /** For sentences: "Room 103 is still waiting to be cleaned." */
    public function phrase(): string
    {
        return match ($this) {
            self::Available => 'clean and ready',
            self::Occupied => 'still occupied',
            self::NeedsCleaning => 'still waiting to be cleaned',
            self::UnderMaintenance => 'under maintenance',
            self::OutOfOrder => 'out of order',
        };
    }

    /** Rooms that cannot take a booking right now (shortcut: status is current, not per date). */
    public function blocksBooking(): bool
    {
        return in_array($this, [self::UnderMaintenance, self::OutOfOrder], true);
    }
}
