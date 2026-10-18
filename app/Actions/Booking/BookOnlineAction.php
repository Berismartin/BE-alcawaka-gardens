<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\DTOs\OnlineBookingDTO;
use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Booking\PublicBookingResource;

class BookOnlineAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(array $input): PublicBookingResource
    {
        $result = $this->service->book(OnlineBookingDTO::fromRequest($input));

        return new PublicBookingResource($result['reservation'], $result['key']);
    }
}
