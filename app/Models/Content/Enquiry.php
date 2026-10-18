<?php

namespace App\Models\Content;

use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use App\Models\Reservations\Reservation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'request_type', 'requested_check_in', 'requested_check_out', 'name', 'phone', 'email', 'event_type', 'event_date', 'guests', 'message', 'status', 'notes'])]
class Enquiry extends Model
{
    use HasUuids;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'event_date' => 'date:Y-m-d',
            'guests' => 'integer',
        ];
    }
}
