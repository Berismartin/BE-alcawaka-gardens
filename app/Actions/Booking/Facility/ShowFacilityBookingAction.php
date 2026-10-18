<?php

namespace App\Actions\Booking\Facility;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Http\Resources\Booking\PublicFacilityBookingResource;

class ShowFacilityBookingAction
{
    public function __construct(private FacilityBookingService $service) {}

    public function execute(string $id, string $key): PublicFacilityBookingResource
    {
        return new PublicFacilityBookingResource($this->service->showForGuest($id, $key)->load('facility.photos'));
    }
}
