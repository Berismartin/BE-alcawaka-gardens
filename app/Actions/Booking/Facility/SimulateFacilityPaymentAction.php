<?php

namespace App\Actions\Booking\Facility;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Http\Resources\Booking\PublicFacilityBookingResource;

class SimulateFacilityPaymentAction
{
    public function __construct(private FacilityBookingService $service) {}

    public function execute(string $id, string $key, bool $approve): PublicFacilityBookingResource
    {
        return new PublicFacilityBookingResource($this->service->simulate($id, $key, $approve)->load('facility.photos'));
    }
}
