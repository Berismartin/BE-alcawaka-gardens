<?php

namespace App\Models\Reservations;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Models\Audit\AuditLog;
use App\Models\Guests\Guest;
use App\Models\Payments\Payment;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number', 'guest_id', 'room_id', 'room_type_id', 'check_in', 'check_out', 'adults', 'children',
    'status', 'source', 'special_requests', 'external_uid', 'channel_id', 'hold_expires_at',
    'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancel_reason', 'created_by',
])]
class Reservation extends Model
{
    use HasUuids;

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class)->orderBy('night');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(FolioCharge::class)->orderBy('posted_on')->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at');
    }

    /** Timeline for the detail page (B05): every audited change to this booking. */
    public function activity(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'subject_id')->where('subject_type', 'reservation')->orderBy('created_at')->orderBy('id');
    }

    public function reference(): string
    {
        return 'ARS-'.$this->number;
    }

    public function nightCount(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    public function totalCharges(): int
    {
        return (int) $this->charges->sum('amount_ugx');
    }

    /** Succeeded payments minus succeeded refunds. */
    public function totalPaid(): int
    {
        return (int) $this->payments
            ->where('status', PaymentStatus::Succeeded)
            ->sum(fn (Payment $p) => $p->signedAmount());
    }

    public function balance(): int
    {
        return $this->totalCharges() - $this->totalPaid();
    }

    protected function casts(): array
    {
        return [
            'check_in' => 'date:Y-m-d',
            'check_out' => 'date:Y-m-d',
            'status' => ReservationStatus::class,
            'source' => ReservationSource::class,
            'adults' => 'integer',
            'children' => 'integer',
            'number' => 'integer',
            'hold_expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
