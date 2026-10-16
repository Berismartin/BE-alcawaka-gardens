<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\DTOs\UpdateFacilityDTO;
use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Http\Resources\Facilities\Facility\FacilityResource;

class UpdateFacilityAction
{
    public function __construct(private FacilityService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): FacilityResource
    {
        return new FacilityResource($this->service->update($id, UpdateFacilityDTO::fromRequest($input)));
    }
}
