<?php

namespace App\Http\Resources\Payments;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Payments\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $reservation = $this->relationLoaded('reservation') ? $this->reservation : null;
        $booking = $this->relationLoaded('facilityBooking') ? $this->facilityBooking : null;

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount_ugx' => $this->amount_ugx,
            'status' => $this->status->value,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'refund_of_id' => $this->refund_of_id,
            'received_at' => $this->received_at?->toISOString(),
            'gateway' => $this->gateway,
            'gateway_details' => $this->gateway ? [
                'tracking_id' => $this->gateway_tracking_id,
                'confirmation_code' => $this->gateway_confirmation_code,
                'paid_with' => $this->gateway_payment_method,
                'account' => $this->gateway_account,
                'checked_at' => $this->gateway_checked_at?->toISOString(),
            ] : null,
            // What staff may do: online payments are confirmed by Pesapal, never approved by hand.
            'can_check' => $this->status === PaymentStatus::Pending && ($this->viaGateway() || ($this->isRefund() && $this->relationLoaded('original') && $this->original?->viaGateway())),
            'can_settle' => $this->status === PaymentStatus::Pending && ! $this->isRefund() && ! $this->viaGateway(),
            'refund_rule' => $this->viaGateway() && ! $this->isRefund()
                ? ($this->isMobileMoney() ? 'Pesapal: one full refund only (mobile money)' : 'Pesapal: one refund, full or part (card)')
                : null,
            'recorded_by' => $this->whenLoaded('recorder', fn () => $this->recorder ? ['id' => $this->recorder->id, 'name' => $this->recorder->name] : null),
            'reservation' => $reservation ? [
                'id' => $reservation->id,
                'reference' => $reservation->reference(),
                'guest_name' => $reservation->guest?->full_name,
                'room_number' => $reservation->room?->number,
            ] : null,
            'facility_booking' => $booking ? [
                'id' => $booking->id,
                'guest_name' => $booking->guest_name,
                'facility_name' => $booking->facility?->name,
            ] : null,
        ];
    }
}
