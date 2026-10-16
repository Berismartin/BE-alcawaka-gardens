<?php

namespace App\Domains\Facilities\FacilityBooking\Enums;

enum FacilityBookingStatus: string
{
    case Confirmed = 'confirmed';
    case PendingPayment = 'pending_payment';
    case Cancelled = 'cancelled';
}
