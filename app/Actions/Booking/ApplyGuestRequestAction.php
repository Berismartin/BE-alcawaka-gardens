<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Content\EnquiryResource;

class ApplyGuestRequestAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $enquiryId): EnquiryResource
    {
        return new EnquiryResource($this->service->applyRequest($enquiryId)->load('reservation:id,number,status'));
    }
}
