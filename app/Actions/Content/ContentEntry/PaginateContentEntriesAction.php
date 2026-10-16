<?php

namespace App\Actions\Content\ContentEntry;

use App\Domains\Content\ContentEntry\Services\ContentService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateContentEntriesAction
{
    public function __construct(private ContentService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
