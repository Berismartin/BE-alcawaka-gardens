<?php

namespace App\Domains\Reservations\Reservation\Enums;

/**
 * pending_payment → confirmed → checked_in → checked_out
 * pending_payment → expired | cancelled
 * confirmed       → cancelled | no_show
 */
enum ReservationStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::PendingPayment => [self::Confirmed, self::Expired, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            default => [],
        }, true);
    }

    /** Holds its room nights (counts against availability). */
    public function holdsRoom(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed, self::CheckedIn, self::CheckedOut], true);
    }

    /** Dates, room and guest count may still change. */
    public function isEditable(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'In house',
            self::CheckedOut => 'Checked out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
            self::Expired => 'Expired',
        };
    }
}
