<?php

namespace App\Domains\Payments\Payment\DTOs;

final readonly class RefundDTO
{
    public function __construct(public int $amountUgx, public string $reason) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self((int) $data['amount_ugx'], trim($data['reason']));
    }
}
