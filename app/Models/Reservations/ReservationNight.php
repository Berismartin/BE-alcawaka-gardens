<?php

namespace App\Models\Reservations;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'room_id', 'night', 'rate_ugx'])]
class ReservationNight extends Model
{
    public $timestamps = false;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    protected function casts(): array
    {
        return ['night' => 'date:Y-m-d', 'rate_ugx' => 'integer'];
    }
}
