<?php

namespace App\Actions\Booking\Facility;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Http\Resources\Booking\PublicFacilityBookingResource;

class PayFacilityBookingAction
{
    public function __construct(private FacilityBookingService $service) {}

    public function execute(string $id, string $key, string $method): PublicFacilityBookingResource
    {
        return new PublicFacilityBookingResource($this->service->payOnline($id, $key, PaymentMethod::from($method))->load('facility.photos'));
    }
}
