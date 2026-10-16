<?php

namespace App\Actions\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateFacilityBookingsAction
{
    public function __construct(private FacilityBookingService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
