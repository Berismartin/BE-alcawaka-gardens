<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\Services\PaymentService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginatePaymentsAction
{
    public function __construct(private PaymentService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
