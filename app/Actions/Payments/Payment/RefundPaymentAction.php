<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\DTOs\RefundDTO;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Http\Resources\Payments\PaymentResource;

class RefundPaymentAction
{
    public function __construct(private PaymentService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): PaymentResource
    {
        return new PaymentResource($this->service->refund($id, RefundDTO::fromRequest($input)));
    }
}
