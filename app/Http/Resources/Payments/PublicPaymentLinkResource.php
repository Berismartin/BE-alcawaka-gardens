<?php

namespace App\Http\Resources\Payments;

use App\Domains\Payments\Gateway\GatewayFee;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Models\Payments\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What the guest sees on /pay/{token}: the invoice, what to pay now, and the latest attempt. Nothing staff-only. @mixin PaymentLink */
class PublicPaymentLinkResource extends JsonResource
{
    /** @param array<string, mixed> $hotel */
    public function __construct(PaymentLink $resource, private array $hotel)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $r = $this->reservation;
        $b = $this->facilityBooking;
        $last = $this->lastPayment;

        return [
            'hotel' => ['name' => $this->hotel['hotel_name'], 'phone' => $this->hotel['phone'] ?? null, 'email' => $this->hotel['email'] ?? null],
            'title' => $r ? "Booking {$r->reference()}" : ($b->facility?->name ?? 'Facility booking'),
            'guest_name' => $r?->guest?->full_name ?? $b?->guest_name,
            'when' => $r
                ? $r->check_in->format('D j M').' – '.$r->check_out->format('D j M Y')
                : $b->starts_at->timezone('Africa/Kampala')->format('D j M Y, H:i').($b->ends_at ? ' – '.$b->ends_at->timezone('Africa/Kampala')->format('D j M, H:i') : ''),
            'lines' => $r
                ? $r->charges->map(fn ($c) => ['description' => $c->description, 'amount_ugx' => $c->amount_ugx])->values()
                : [['description' => ($b->facility?->name ?? 'Facility').' · '.$b->party_size.' '.($b->party_size === 1 ? 'person' : 'people'), 'amount_ugx' => $b->amount_ugx]],
            'total_ugx' => $r ? $r->totalCharges() : $b->amount_ugx,
            'paid_ugx' => $r ? $r->totalPaid() : $b->paidAmount(),
            'due_ugx' => PaymentLinkService::due($this->owner()),
            'pay_now_ugx' => $payNow = PaymentLinkService::payNow($this->resource),
            // Pesapal's fee added on top when the hotel passes it to the guest (none in test mode)
            'fee_ugx' => config('payments.simulator') ? 0 : (GatewayFee::fromSettings(app(SettingsService::class))->quote($payNow)['charge_ugx'] - $payNow),
            'message' => $this->message,
            'status' => PaymentLinkService::status($this->resource),
            'expires_at' => $this->expires_at->toISOString(),
            'last_payment' => $last ? [
                'status' => $last->status->value,
                'amount_ugx' => $last->amount_ugx,
                'method_label' => $last->method->label(),
                'redirect_url' => $last->status === PaymentStatus::Pending ? $last->gateway_redirect_url : null,
            ] : null,
            'simulator' => (bool) config('payments.simulator'),
        ];
    }
}
