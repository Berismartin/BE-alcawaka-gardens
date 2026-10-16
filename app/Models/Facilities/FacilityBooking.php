<?php

namespace App\Models\Facilities;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'facility_id', 'reservation_id', 'guest_name', 'guest_phone', 'starts_at', 'party_size',
    'charge_mode', 'amount_ugx', 'status', 'notes', 'created_by',
])]
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

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'party_size' => 'integer',
            'amount_ugx' => 'integer',
            'charge_mode' => ChargeMode::class,
            'status' => FacilityBookingStatus::class,
        ];
    }
}
