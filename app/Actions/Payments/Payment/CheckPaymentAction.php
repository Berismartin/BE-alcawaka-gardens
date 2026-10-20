<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\Services\OnlinePaymentService;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Exceptions\EntityValidationException;
use App\Http\Resources\Payments\PaymentResource;

/** Staff "Check with Pesapal" on a pending online payment or refund. */
class CheckPaymentAction
{
    public function __construct(private PaymentService $payments, private OnlinePaymentService $online) {}

    public function execute(string $id): PaymentResource
    {
        $payment = $this->payments->find($id);
        $online = $payment->isRefund() ? $payment->original?->viaGateway() : $payment->viaGateway();
        if (! $online) {
            throw new EntityValidationException('Only online payments can be checked with Pesapal.');
        }

        return new PaymentResource($this->online->sync($payment));
    }
}
