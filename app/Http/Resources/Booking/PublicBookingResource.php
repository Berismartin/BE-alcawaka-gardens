<?php

namespace App\Http\Resources\Booking;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * What a guest may see about their own booking: no room number, staff names or ID documents.
 *
 * @mixin Reservation
 */
class PublicBookingResource extends JsonResource
{
    public function __construct(Reservation $resource, private ?string $key = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $latest = $this->payments->where('kind', 'payment')->sortBy('id')->last(); // ids are time-ordered UUIDs
        $photo = $this->roomType->photos->first();

        return [
            'reference' => $this->reference(),
            'key' => $this->when($this->key !== null, $this->key),
            'status' => $this->status->value,
            'source' => $this->source->value,
            'check_in' => $this->check_in->toDateString(),
            'check_out' => $this->check_out->toDateString(),
            'nights' => $this->nightCount(),
            'adults' => $this->adults,
            'children' => $this->children,
            'special_requests' => $this->special_requests,
            'room_type' => ['name' => $this->roomType->name, 'slug' => $this->roomType->slug, 'photo' => $photo ? Storage::disk($photo->disk)->url($photo->path) : null],
            'guest' => ['full_name' => $this->guest->full_name, 'email' => $this->guest->email, 'phone' => $this->guest->phone],
            'charges' => $this->charges->map(fn ($c) => ['description' => $c->description, 'amount_ugx' => $c->amount_ugx])->values(),
            'totals' => ['charges_ugx' => $this->totalCharges(), 'paid_ugx' => $this->totalPaid(), 'balance_ugx' => $this->balance()],
            'hold_expires_at' => $this->hold_expires_at?->toISOString(),
            'payment' => $latest ? [
                'method' => $latest->method->value,
                'method_label' => $latest->method->label(),
                'status' => $latest->status->value,
                'amount_ugx' => $latest->amount_ugx,
                'reference' => $latest->reference,
            ] : null,
            'paid_with' => $this->payments->where('status', PaymentStatus::Succeeded)->where('kind', 'payment')->map(fn (Payment $p) => $p->method->label())->unique()->values(),
            'simulator' => (bool) config('payments.simulator'),
        ];
    }
}
