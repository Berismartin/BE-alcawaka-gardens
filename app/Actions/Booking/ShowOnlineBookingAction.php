<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Booking\PublicBookingResource;

class ShowOnlineBookingAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, string $key): PublicBookingResource
    {
        return new PublicBookingResource($this->service->show($reference, $key));
    }
}
