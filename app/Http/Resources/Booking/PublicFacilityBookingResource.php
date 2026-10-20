<?php

namespace App\Http\Resources\Booking;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Facilities\FacilityBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * What a guest may see about their own facility booking.
 *
 * @mixin FacilityBooking
 */
class PublicFacilityBookingResource extends JsonResource
{
    public function __construct(FacilityBooking $resource, private ?string $key = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $latest = $this->payments->where('kind', 'payment')->sortBy('id')->last(); // ids are time-ordered UUIDs
        $photo = $this->facility->photos->first();

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'key' => $this->when($this->key !== null, $this->key),
            'status' => $this->status->value,
            'facility' => ['name' => $this->facility->name, 'photo' => $photo ? Storage::disk($photo->disk)->url($photo->path) : null],
            'starts_at' => $this->starts_at->toISOString(),
            'party_size' => $this->party_size,
            'amount_ugx' => $this->amount_ugx,
            'paid_ugx' => $this->paidAmount(),
            'pay_at_venue' => $this->charge_mode === ChargeMode::PayLater,
            'notes' => $this->notes,
            'guest' => ['full_name' => $this->guest_name, 'email' => $this->guest_email, 'phone' => $this->guest_phone],
            'hold_expires_at' => $this->hold_expires_at?->toISOString(),
            'payment' => $latest ? [
                'method' => $latest->method->value,
                'method_label' => $latest->method->label(),
                'status' => $latest->status->value,
                'amount_ugx' => $latest->amount_ugx,
                'fee_ugx' => $latest->guestFee(), // Pesapal's fee charged on top, when the guest pays it
                'gateway' => $latest->gateway,
                // Pesapal's hosted page, while the payment can still be made there
                'redirect_url' => $latest->status === PaymentStatus::Pending ? $latest->gateway_redirect_url : null,
            ] : null,
            'simulator' => (bool) config('payments.simulator'),
        ];
    }
}
