<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Http\Resources\Facilities\Facility\FacilityResource;

class ShowFacilityAction
{
    public function __construct(private FacilityService $service) {}

    public function execute(string $id): FacilityResource
    {
        return new FacilityResource($this->service->find($id));
    }
}
