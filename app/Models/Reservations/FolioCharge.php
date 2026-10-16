<?php

namespace App\Models\Reservations;

use App\Domains\Reservations\Reservation\Enums\ChargeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['reservation_id', 'category', 'description', 'amount_ugx', 'posted_on', 'created_by'])]
class FolioCharge extends Model
{
    use HasUuids;

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['category' => ChargeCategory::class, 'amount_ugx' => 'integer', 'posted_on' => 'date:Y-m-d'];
    }
}
