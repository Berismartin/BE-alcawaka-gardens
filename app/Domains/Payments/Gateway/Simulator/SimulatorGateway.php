<?php

namespace App\Domains\Payments\Gateway\Simulator;

use App\Domains\Payments\Gateway\DTOs\CheckoutRequest;
use App\Domains\Payments\Gateway\DTOs\CheckoutSession;
use App\Domains\Payments\Gateway\DTOs\GatewayRefundRequest;
use App\Domains\Payments\Gateway\DTOs\GatewayStatus;
use App\Domains\Payments\Gateway\Enums\GatewayState;
use App\Domains\Payments\Gateway\PaymentGateway;

/**
 * Local development and tests: no hosted page. The booking page's test panel approves or declines
 * the payment (POST .../simulate), standing in for the guest's phone or card.
 */
class SimulatorGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'simulator';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        return new CheckoutSession(trackingId: null, redirectUrl: null);
    }

    public function fetchStatus(string $trackingId): GatewayStatus
    {
        return new GatewayStatus(GatewayState::Pending);
    }

    public function requestRefund(GatewayRefundRequest $request): void {}

    public function cancel(string $trackingId): void {}
}
