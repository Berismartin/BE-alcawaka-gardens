<?php

namespace App\Actions\Booking;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Booking\PublicBookingResource;

class PayOnlineBookingAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, string $key, string $method): PublicBookingResource
    {
        return new PublicBookingResource($this->service->pay($reference, $key, PaymentMethod::from($method)));
    }
}
