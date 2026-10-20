<?php

namespace App\Models\Payments;

use App\Models\Facilities\FacilityBooking;
use App\Models\Reservations\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['token', 'reservation_id', 'facility_booking_id', 'amount_ugx', 'email', 'message', 'last_payment_id', 'sent_by', 'expires_at'])]
#[Hidden(['token'])]
class PaymentLink extends Model
{
    use HasUuids;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function facilityBooking(): BelongsTo
    {
        return $this->belongsTo(FacilityBooking::class);
    }

    public function lastPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'last_payment_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function owner(): Reservation|FacilityBooking
    {
        return $this->reservation ?? $this->facilityBooking;
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/')."/pay/{$this->token}";
    }

    protected function casts(): array
    {
        return ['amount_ugx' => 'integer', 'expires_at' => 'datetime'];
    }
}
