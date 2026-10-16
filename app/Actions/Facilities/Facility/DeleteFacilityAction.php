<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\Services\FacilityService;

class DeleteFacilityAction
{
    public function __construct(private FacilityService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
