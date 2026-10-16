<?php

namespace App\Domains\Facilities\FacilityBooking\DTOs;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;
use App\Domains\Payments\Payment\Enums\PaymentMethod;

final readonly class CreateFacilityBookingDTO
{
    public function __construct(
        public string $facilityId,
        public string $startsAt,
        public int $partySize,
        public ChargeMode $chargeMode,
        public ?string $reservationId = null,
        public ?string $guestName = null,
        public ?string $guestPhone = null,
        public ?int $amountUgx = null,
        public ?PaymentMethod $paymentMethod = null,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            facilityId: $data['facility_id'],
            startsAt: $data['starts_at'],
            partySize: (int) $data['party_size'],
            chargeMode: ChargeMode::from($data['charge_mode']),
            reservationId: $data['reservation_id'] ?? null,
            guestName: isset($data['guest_name']) ? trim($data['guest_name']) : null,
            guestPhone: PhoneNumber::normalize($data['guest_phone'] ?? null),
            amountUgx: isset($data['amount_ugx']) ? (int) $data['amount_ugx'] : null,
            paymentMethod: isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null,
            notes: $data['notes'] ?? null,
        );
    }
}
