<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;

class GuestRequestAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, array $input): void
    {
        $this->service->request($reference, $input['key'], $input['type'], $input['new_check_in'] ?? null, $input['new_check_out'] ?? null, $input['note'] ?? null);
    }
}
