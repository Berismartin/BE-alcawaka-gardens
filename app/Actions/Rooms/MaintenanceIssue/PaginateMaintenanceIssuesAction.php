<?php

namespace App\Actions\Rooms\MaintenanceIssue;

use App\Domains\Rooms\MaintenanceIssue\Services\MaintenanceService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateMaintenanceIssuesAction
{
    public function __construct(private MaintenanceService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
