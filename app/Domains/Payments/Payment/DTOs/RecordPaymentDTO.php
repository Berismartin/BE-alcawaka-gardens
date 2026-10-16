<?php

namespace App\Domains\Payments\Payment\DTOs;

use App\Domains\Payments\Payment\Enums\PaymentMethod;

final readonly class RecordPaymentDTO
{
    public function __construct(
        public PaymentMethod $method,
        public int $amountUgx,
        public ?string $reference = null,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            method: PaymentMethod::from($data['method']),
            amountUgx: (int) $data['amount_ugx'],
            reference: isset($data['reference']) ? trim($data['reference']) ?: null : null,
            notes: $data['notes'] ?? null,
        );
    }
}
