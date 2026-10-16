<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\Services\PaymentService;

class PaymentSummaryAction
{
    public function __construct(private PaymentService $service) {}

    /** @return array<string, mixed> */
    public function execute(?string $date): array
    {
        return $this->service->summary($date);
    }
}
