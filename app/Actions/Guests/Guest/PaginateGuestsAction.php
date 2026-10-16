<?php

namespace App\Actions\Guests\Guest;

use App\Domains\Guests\Guest\Services\GuestService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateGuestsAction
{
    public function __construct(private GuestService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
