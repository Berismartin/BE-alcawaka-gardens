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

    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_id');
    }

    public function isRefund(): bool
    {
        return $this->kind === 'refund';
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
        ];
    }
}
