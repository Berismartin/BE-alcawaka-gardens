<?php

namespace App\Http\Requests\Payments;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['received_at', 'amount_ugx'];
    }

    protected function filters(): array
    {
        return [
            'method' => [Rule::enum(PaymentMethod::class)],
            'status' => [Rule::enum(PaymentStatus::class)],
            'kind' => [Rule::in(['payment', 'refund'])],
            'reservation_id' => ['uuid'],
        ];
    }
}
