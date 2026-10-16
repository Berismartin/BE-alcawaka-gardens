<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\DTOs\CreateFacilityDTO;
use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Http\Resources\Facilities\Facility\FacilityResource;

class CreateFacilityAction
{
    public function __construct(private FacilityService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): FacilityResource
    {
        return new FacilityResource($this->service->create(CreateFacilityDTO::fromRequest($input)));
    }
}
