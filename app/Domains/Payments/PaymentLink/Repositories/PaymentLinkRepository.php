<?php

namespace App\Domains\Payments\PaymentLink\Repositories;

use App\Models\Payments\PaymentLink;

class PaymentLinkRepository implements PaymentLinkRepositoryInterface
{
    private const WITH = ['reservation.charges', 'reservation.payments', 'reservation.guest', 'reservation.roomType', 'facilityBooking.payments', 'facilityBooking.facility', 'lastPayment'];

    public function findByToken(string $token): ?PaymentLink
    {
        return PaymentLink::with(self::WITH)->where('token', $token)->first();
    }

    public function create(array $data): PaymentLink
    {
        return PaymentLink::create($data)->load(self::WITH);
    }

    public function update(PaymentLink $link, array $data): PaymentLink
    {
        $link->update($data);

        return $link->refresh()->load(self::WITH);
    }
}
