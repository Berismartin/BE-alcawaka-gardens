<?php

namespace App\Http\Resources\Reservations;

use App\Http\Resources\Audit\AuditLogResource;
use App\Http\Resources\Guests\GuestResource;
use App\Http\Resources\Payments\PaymentResource;
use App\Models\Reservations\FolioCharge;
use App\Models\Reservations\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * List rows carry totals from SQL sums (charges_total, paid_total); the detail
 * view loads charges, payments and activity and computes them.
 *
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $this->relationLoaded('charges') && $this->relationLoaded('payments');
        $charges = $detail ? $this->totalCharges() : (int) ($this->charges_total ?? 0);
        $paid = $detail ? $this->totalPaid() : (int) ($this->paid_total ?? 0);

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'source' => $this->source->value,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nightCount(),
            'adults' => $this->adults,
            'children' => $this->children,
            'special_requests' => $this->special_requests,
            'guest' => $this->whenLoaded('guest', fn () => $detail
                ? new GuestResource($this->guest)
                : ['id' => $this->guest->id, 'full_name' => $this->guest->full_name, 'phone' => $this->guest->phone ?? null, 'email' => $this->guest->email ?? null]),
            'room' => $this->whenLoaded('room', fn () => [
                'id' => $this->room->id,
                'number' => $this->room->number,
                'status' => $this->room->status?->value,
            ]),
            'room_type' => $this->whenLoaded('roomType', fn () => ['id' => $this->roomType->id, 'name' => $this->roomType->name]),
            'totals' => ['charges_ugx' => $charges, 'paid_ugx' => $paid, 'balance_ugx' => $charges - $paid],
            'hold_expires_at' => $this->hold_expires_at?->toISOString(),
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'checked_out_at' => $this->checked_out_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'cancel_reason' => $this->cancel_reason,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at?->toISOString(),
            'charges' => $this->whenLoaded('charges', fn () => $this->charges->map(fn (FolioCharge $c) => [
                'id' => $c->id,
                'category' => $c->category->value,
                'description' => $c->description,
                'amount_ugx' => $c->amount_ugx,
                'posted_on' => $c->posted_on->toDateString(),
                'created_by' => $c->relationLoaded('creator') ? $c->creator?->name : null,
            ])),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'activity' => AuditLogResource::collection($this->whenLoaded('activity')),
        ];
    }
}
