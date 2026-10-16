<?php

namespace App\Domains\Reservations\Reservation\DTOs;

use App\Domains\Guests\Guest\DTOs\GuestDTO;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;

final readonly class CreateReservationDTO
{
    public function __construct(
        public StayDTO $stay,
        public string $roomId,
        public ReservationSource $source,
        public ?string $guestId = null,
        public ?GuestDTO $guest = null,
        public ?string $specialRequests = null,
        public ?RecordPaymentDTO $payment = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            stay: StayDTO::fromRequest($data),
            roomId: $data['room_id'],
            source: ReservationSource::from($data['source'] ?? ReservationSource::FrontDesk->value),
            guestId: $data['guest_id'] ?? null,
            guest: isset($data['guest']) ? GuestDTO::fromRequest($data['guest']) : null,
            specialRequests: $data['special_requests'] ?? null,
            payment: isset($data['payment']) ? RecordPaymentDTO::fromRequest($data['payment']) : null,
        );
    }
}
