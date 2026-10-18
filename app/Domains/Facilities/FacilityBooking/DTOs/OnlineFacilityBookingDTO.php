<?php

namespace App\Domains\Facilities\FacilityBooking\DTOs;

use App\Domains\Payments\Payment\Enums\PaymentMethod;

final readonly class OnlineFacilityBookingDTO
{
    public function __construct(
        public string $facilityId,
        public string $startsAt,
        public int $partySize,
        public string $fullName,
        public string $phone,
        public string $email,
        public PaymentMethod $paymentMethod,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            facilityId: $data['facility_id'],
            startsAt: "{$data['date']} {$data['time']}",
            partySize: (int) $data['party_size'],
            fullName: trim($data['guest']['full_name']),
            phone: trim($data['guest']['phone']),
            email: trim($data['guest']['email']),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            notes: isset($data['notes']) ? trim($data['notes']) ?: null : null,
        );
    }
}
