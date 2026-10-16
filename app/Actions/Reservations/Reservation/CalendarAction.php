<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Rooms\RoomResource;

class CalendarAction
{
    public function __construct(private ReservationService $service) {}

    /** @return array<string, mixed> */
    public function execute(string $from, int $days): array
    {
        $data = $this->service->calendar($from, $days);

        return [
            'from' => $from,
            'days' => $days,
            'rooms' => RoomResource::collection($data['rooms']),
            'bookings' => $data['bookings']->map(fn ($r) => [
                'id' => $r->id,
                'reference' => $r->reference(),
                'room_id' => $r->room_id,
                'guest_name' => $r->guest->full_name,
                'check_in' => $r->check_in->toDateString(),
                'check_out' => $r->check_out->toDateString(),
                'status' => $r->status->value,
                'balance_ugx' => (int) $r->charges_total - (int) $r->paid_total,
            ])->values(),
        ];
    }
}
