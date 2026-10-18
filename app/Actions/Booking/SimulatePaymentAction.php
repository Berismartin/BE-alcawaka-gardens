<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Booking\PublicBookingResource;

class SimulatePaymentAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, string $key, bool $approve): PublicBookingResource
    {
        return new PublicBookingResource($this->service->simulate($reference, $key, $approve));
    }
}
