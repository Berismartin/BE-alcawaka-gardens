<?php

namespace App\Domains\Payments\Gateway\DTOs;

use App\Domains\Payments\Gateway\Enums\GatewayState;

final readonly class GatewayStatus
{
    public function __construct(
        public GatewayState $state,
        /** What the guest actually paid, in whole shillings. */
        public ?int $amountUgx = null,
        public ?string $currency = null,
        public ?string $confirmationCode = null,
        /** e.g. "MTN", "Airtel", "Visa" */
        public ?string $paymentMethod = null,
        /** Masked phone or card number */
        public ?string $account = null,
        public ?string $merchantReference = null,
        public ?string $description = null,
    ) {}
}
