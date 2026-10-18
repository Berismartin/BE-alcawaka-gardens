<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\DTOs\StayDTO;
use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use App\Http\Resources\Rooms\RoomTypeResource;

class SearchRoomsAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(array $input): array
    {
        $rows = $this->service->search(StayDTO::fromRequest($input));

        return array_map(fn ($r) => [
            'room_type' => new RoomTypeResource($r['type']),
            'rooms_left' => $r['rooms_left'],
            'nightly' => collect($r['nightly'])->map(fn ($rate, $night) => ['night' => $night, 'rate_ugx' => $rate])->values(),
            'total_ugx' => $r['total_ugx'],
            'fits' => $r['fits'],
        ], $rows);
    }
}
