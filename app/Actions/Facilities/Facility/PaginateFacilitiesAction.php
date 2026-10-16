<?php

namespace App\Actions\Facilities\Facility;

use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateFacilitiesAction
{
    public function __construct(private FacilityService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
