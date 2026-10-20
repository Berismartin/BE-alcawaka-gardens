<?php

namespace App\Models\Content;

use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use App\Models\Reservations\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'request_type', 'requested_check_in', 'requested_check_out', 'name', 'phone', 'email', 'event_type', 'event_date', 'guests', 'message', 'status', 'notes', 'attended_by', 'attended_at'])]
class Enquiry extends Model
{
    use HasUuids;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /** The staff member who first attended to it. */
    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    protected function casts(): array
    {
        return [
            'attended_at' => 'datetime',
            'status' => EnquiryStatus::class,
            'event_date' => 'date:Y-m-d',
            'guests' => 'integer',
        ];
    }
}
