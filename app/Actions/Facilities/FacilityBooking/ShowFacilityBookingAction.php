<?php

namespace App\Actions\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Http\Resources\Facilities\FacilityBooking\FacilityBookingResource;

class ShowFacilityBookingAction
{
    public function __construct(private FacilityBookingService $service) {}

    public function execute(string $id): FacilityBookingResource
    {
        return new FacilityBookingResource($this->service->find($id));
    }
}
