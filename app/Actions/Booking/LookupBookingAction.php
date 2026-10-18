<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Booking\PublicBookingResource;

class LookupBookingAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, string $contact): PublicBookingResource
    {
        $reservation = $this->service->lookup($reference, $contact);

        return new PublicBookingResource($reservation, $reservation->guest_key);
    }
}
