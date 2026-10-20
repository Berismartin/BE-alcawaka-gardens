<?php

namespace App\Models\Payments;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Concerns\HasMedia;
use App\Models\Facilities\FacilityBooking;
use App\Models\Reservations\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reservation_id', 'facility_booking_id', 'refund_of_id', 'kind', 'method', 'amount_ugx',
    'status', 'reference', 'notes', 'recorded_by', 'received_at',
    'gateway', 'gateway_tracking_id', 'gateway_redirect_url', 'gateway_confirmation_code',
    'gateway_payment_method', 'gateway_account', 'gateway_checked_at',
])]
class Payment extends Model
{
    use HasMedia, HasUuids;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function facilityBooking(): BelongsTo
    {
        return $this->belongsTo(FacilityBooking::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** For a refund: the payment it returns. */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_id');
    }

    public function isRefund(): bool
    {
        return $this->kind === 'refund';
    }

    /** Paid through an online gateway: only the gateway can say whether the money arrived. */
    public function viaGateway(): bool
    {
        return $this->gateway !== null && $this->gateway_tracking_id !== null;
    }

    /** Pesapal only refunds mobile money in full; cards may be partly refunded. */
    public function isMobileMoney(): bool
    {
        return in_array($this->method, [PaymentMethod::MtnMomo, PaymentMethod::AirtelMoney], true);
    }

    /** Positive for money in, negative for refunds. */
    public function signedAmount(): int
    {
        return $this->isRefund() ? -$this->amount_ugx : $this->amount_ugx;
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_ugx' => 'integer',
            'received_at' => 'datetime',
            'gateway_checked_at' => 'datetime',
        ];
    }
}
