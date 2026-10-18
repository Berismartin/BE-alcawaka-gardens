<?php

namespace App\Domains\Content\Enquiry\DTOs;

use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;

final readonly class CreateEnquiryDTO
{
    public function __construct(
        public string $name,
        public string $phone,
        public string $message,
        public ?string $email = null,
        public ?string $eventType = null,
        public ?string $eventDate = null,
        public ?int $guests = null,
        public ?string $reservationId = null,
        public ?string $requestType = null,
        public ?string $requestedCheckIn = null,
        public ?string $requestedCheckOut = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            phone: PhoneNumber::normalize($data['phone']) ?? $data['phone'], // same +256 form as guests, so staff can search and call it
            message: $data['message'],
            email: $data['email'] ?? null,
            eventType: $data['event_type'] ?? null,
            eventDate: $data['event_date'] ?? null,
            guests: isset($data['guests']) ? (int) $data['guests'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'event_type' => $this->eventType,
            'event_date' => $this->eventDate,
            'guests' => $this->guests,
            'message' => $this->message,
            'reservation_id' => $this->reservationId,
            'request_type' => $this->requestType,
            'requested_check_in' => $this->requestedCheckIn,
            'requested_check_out' => $this->requestedCheckOut,
        ];
    }
}
