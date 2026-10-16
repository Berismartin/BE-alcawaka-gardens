<?php

namespace App\Actions\Content\Enquiry;

use App\Domains\Content\Enquiry\Services\EnquiryService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateEnquiriesAction
{
    public function __construct(private EnquiryService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
