<?php

namespace App\Actions\Payments\Online;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;

class PayPaymentLinkAction
{
    public function __construct(private PaymentLinkService $links) {}

    /** @return array{status: string, redirect_url: ?string} */
    public function execute(string $token, string $method): array
    {
        $payment = $this->links->checkout($token, PaymentMethod::from($method));

        return ['status' => $payment->status->value, 'redirect_url' => $payment->gateway_redirect_url];
    }
}
