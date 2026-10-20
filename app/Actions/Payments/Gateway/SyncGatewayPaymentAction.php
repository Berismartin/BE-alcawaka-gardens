<?php

namespace App\Actions\Payments\Gateway;

use App\Domains\Payments\Payment\Services\OnlinePaymentService;
use App\Models\Payments\Payment;

/** Pesapal's IPN: ask Pesapal for the order's real status and apply it. */
class SyncGatewayPaymentAction
{
    public function __construct(private OnlinePaymentService $service) {}

    public function execute(string $trackingId): Payment
    {
        return $this->service->syncByTrackingId($trackingId);
    }
}
