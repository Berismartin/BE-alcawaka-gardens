<?php

namespace App\Actions\Staff;

use App\Domains\Users\User\Services\StaffService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateStaffAction
{
    public function __construct(private StaffService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
