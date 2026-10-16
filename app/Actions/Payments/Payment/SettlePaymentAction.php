<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Http\Resources\Payments\PaymentResource;

class SettlePaymentAction
{
    public function __construct(private PaymentService $service) {}

    public function execute(string $id, string $status): PaymentResource
    {
        return new PaymentResource($this->service->settle($id, PaymentStatus::from($status)));
    }
}
