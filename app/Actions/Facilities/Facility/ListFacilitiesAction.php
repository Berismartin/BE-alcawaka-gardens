<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Http\Resources\Facilities\Facility\FacilityResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListFacilitiesAction
{
    public function __construct(private FacilityService $service) {}

    public function execute(bool $activeOnly = false): AnonymousResourceCollection
    {
        return FacilityResource::collection($this->service->list($activeOnly));
    }
}
