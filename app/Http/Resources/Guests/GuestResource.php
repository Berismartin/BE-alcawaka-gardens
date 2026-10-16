<?php

namespace App\Http\Resources\Guests;

use App\Http\Resources\Media\MediaResource;
use App\Models\Guests\Guest;
use App\Models\Reservations\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guest */
class GuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'nationality' => $this->nationality,
            'id_number' => $this->id_number,
            'notes' => $this->notes,
            'reservations_count' => $this->whenCounted('reservations'),
            'last_stay_on' => $this->when(isset($this->last_stay_on), fn () => substr((string) $this->last_stay_on, 0, 10)),
            'documents' => MediaResource::collection($this->whenLoaded('documents')),
            'reservations' => $this->whenLoaded('reservations', fn () => $this->reservations->map(fn (Reservation $r) => [
                'id' => $r->id,
                'reference' => $r->reference(),
                'check_in' => $r->check_in->toDateString(),
                'check_out' => $r->check_out->toDateString(),
                'status' => $r->status->value,
                'room_number' => $r->room?->number,
            ])),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
