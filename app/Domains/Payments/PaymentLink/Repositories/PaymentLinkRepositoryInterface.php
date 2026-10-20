<?php

namespace App\Domains\Payments\PaymentLink\Repositories;

use App\Models\Payments\PaymentLink;

interface PaymentLinkRepositoryInterface
{
    public function findByToken(string $token): ?PaymentLink;

    /** @param array<string, mixed> $data */
    public function create(array $data): PaymentLink;

    /** @param array<string, mixed> $data */
    public function update(PaymentLink $link, array $data): PaymentLink;
}
