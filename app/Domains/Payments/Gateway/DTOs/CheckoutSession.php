<?php

namespace App\Domains\Payments\Gateway\DTOs;

final readonly class CheckoutSession
{
    public function __construct(
        public ?string $trackingId,
        public ?string $redirectUrl,
    ) {}
}
