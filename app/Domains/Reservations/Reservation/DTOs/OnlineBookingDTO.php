<?php

namespace App\Domains\Reservations\Reservation\DTOs;

use App\Domains\Guests\Guest\DTOs\GuestDTO;
use App\Domains\Payments\Payment\Enums\PaymentMethod;

/** A guest booking on the website: a room type (we pick the room), their details and how they'll pay. */
final readonly class OnlineBookingDTO
{
    public function __construct(
        public StayDTO $stay,
        public string $roomTypeId,
        public GuestDTO $guest,
        public PaymentMethod $paymentMethod,
        public ?string $arrivalTime = null,
        public ?string $specialRequests = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            stay: StayDTO::fromRequest($data),
            roomTypeId: $data['room_type_id'],
            guest: GuestDTO::fromRequest($data['guest']),
            paymentMethod: PaymentMethod::from($data['payment_method']),
            arrivalTime: $data['arrival_time'] ?? null,
            specialRequests: isset($data['special_requests']) ? trim($data['special_requests']) ?: null : null,
        );
    }
}
