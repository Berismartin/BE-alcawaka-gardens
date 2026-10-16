<?php

namespace App\Http\Resources\Reservations;

use App\Http\Resources\Rooms\RoomResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One bookable room for a stay: the room, each night's rate and the total. */
class AvailableRoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'room' => new RoomResource($this->resource['room']),
            'nightly' => collect($this->resource['nightly'])->map(fn ($rate, $night) => ['night' => $night, 'rate_ugx' => $rate])->values(),
            'total_ugx' => $this->resource['total_ugx'],
        ];
    }
}
