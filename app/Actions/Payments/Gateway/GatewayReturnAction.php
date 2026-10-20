<?php

namespace App\Actions\Payments\Gateway;

use App\Domains\Payments\Payment\Services\OnlinePaymentService;

/**
 * The guest is back from Pesapal's page: settle now (no waiting for the IPN) and tell the website which
 * booking page to open. Only the guest and Pesapal know the order id, so it can carry the booking key.
 */
class GatewayReturnAction
{
    public function __construct(private OnlinePaymentService $service) {}

    /** @return array{type: string, reference: ?string, id: string, key: ?string, status: string} */
    public function execute(string $trackingId): array
    {
        $payment = $this->service->syncByTrackingId($trackingId);
        $payment->load(['reservation', 'facilityBooking']); // full rows, including the guest key
        $reservation = $payment->reservation;
        $booking = $payment->facilityBooking;

        return [
            'type' => $reservation ? 'room' : 'facility',
            'reference' => $reservation?->reference(),
            'id' => $reservation?->id ?? $booking->id,
            'key' => $reservation?->guest_key ?? $booking?->guest_key,
            'status' => $payment->status->value,
        ];
    }
}
