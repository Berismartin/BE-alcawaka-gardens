<?php

namespace App\Domains\Payments\Gateway\DTOs;

final readonly class GatewayRefundRequest
{
    public function __construct(
        public string $confirmationCode,
        public int $amountUgx,
        /** Who asked for it, as Pesapal records it */
        public string $requestedBy,
        public string $reason,
    ) {}
}
