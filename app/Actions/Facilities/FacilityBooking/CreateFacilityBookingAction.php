<?php

namespace App\Actions\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\DTOs\CreateFacilityBookingDTO;
use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Http\Resources\Facilities\FacilityBooking\FacilityBookingResource;

class CreateFacilityBookingAction
{
    public function __construct(private FacilityBookingService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): FacilityBookingResource
    {
        return new FacilityBookingResource($this->service->create(CreateFacilityBookingDTO::fromRequest($input)));
    }
}
