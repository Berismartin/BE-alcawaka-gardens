<?php

namespace App\Models\Facilities;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'facility_id', 'reservation_id', 'guest_name', 'guest_phone', 'guest_email', 'starts_at', 'party_size',
    'charge_mode', 'amount_ugx', 'status', 'notes', 'created_by', 'guest_key', 'hold_expires_at',
])]
#[Hidden(['guest_key'])]
class FacilityBooking extends Model
{
    use HasUuids;

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Short code the guest can quote at the gate. */
    public function reference(): string
    {
        return 'FB-'.strtoupper(substr(str_replace('-', '', $this->id), -6));
    }

    public function paidAmount(): int
    {
        return (int) $this->payments->where('status', PaymentStatus::Succeeded)->sum(fn (Payment $p) => $p->signedAmount());
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'guest_key' => 'encrypted',
            'party_size' => 'integer',
            'amount_ugx' => 'integer',
            'charge_mode' => ChargeMode::class,
            'status' => FacilityBookingStatus::class,
        ];
    }
}
