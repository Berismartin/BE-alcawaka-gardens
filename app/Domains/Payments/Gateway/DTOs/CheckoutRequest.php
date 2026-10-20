<?php

namespace App\Domains\Payments\Gateway\DTOs;

final readonly class CheckoutRequest
{
    public function __construct(
        /** Our payment id: unique per attempt, so a retry can never charge the same order twice. */
        public string $merchantReference,
        public int $amountUgx,
        public string $description,
        public string $callbackUrl,
        public string $cancellationUrl,
        public ?string $email,
        public ?string $phone,
        public string $firstName,
        public string $lastName,
    ) {}
}
