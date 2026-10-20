<?php

namespace App\Domains\Payments\Gateway;

use App\Domains\Payments\Gateway\DTOs\CheckoutRequest;
use App\Domains\Payments\Gateway\DTOs\CheckoutSession;
use App\Domains\Payments\Gateway\DTOs\GatewayRefundRequest;
use App\Domains\Payments\Gateway\DTOs\GatewayStatus;
use App\Exceptions\GatewayException;

/**
 * An online payment provider (Pesapal, or the local simulator). The payment services depend on this
 * contract only, so a provider can be swapped in config without touching booking or payment rules.
 * Every method may throw GatewayException; none of them is called inside a database transaction.
 */
interface PaymentGateway
{
    /** Short name stored on each payment, e.g. "pesapal". */
    public function name(): string;

    /** Ask the provider for a hosted payment page. A null trackingId means "no hosted page" (simulator). @throws GatewayException */
    public function createCheckout(CheckoutRequest $request): CheckoutSession;

    /** The provider's current truth about one order. Never trust a callback without asking this. @throws GatewayException */
    public function fetchStatus(string $trackingId): GatewayStatus;

    /** Ask the provider to return money on a completed payment. @throws GatewayException when refused */
    public function requestRefund(GatewayRefundRequest $request): void;

    /** Stop a pending order from being paid later (best effort). @throws GatewayException */
    public function cancel(string $trackingId): void;
}
