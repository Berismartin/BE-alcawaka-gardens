<?php

namespace App\Http\Resources\Facilities\FacilityBooking;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Facilities\FacilityBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FacilityBooking */
class FacilityBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paid = $this->relationLoaded('payments') ? $this->payments->firstWhere('status', PaymentStatus::Succeeded) : null;

        return [
            'id' => $this->id,
            'facility' => $this->whenLoaded('facility', fn () => ['id' => $this->facility->id, 'name' => $this->facility->name]),
            'reservation' => $this->whenLoaded('reservation', fn () => $this->reservation ? [
                'id' => $this->reservation->id,
                'reference' => $this->reservation->reference(),
                'room_number' => $this->reservation->room?->number,
            ] : null),
            'guest_name' => $this->guest_name,
            'guest_phone' => $this->guest_phone,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'party_size' => $this->party_size,
            'charge_mode' => $this->charge_mode->value,
            'amount_ugx' => $this->amount_ugx,
            'paid_with' => $paid?->method->label(),
            'status' => $this->status->value,
            'notes' => $this->notes,
        ];
    }
}
