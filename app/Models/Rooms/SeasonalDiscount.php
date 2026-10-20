<?php

namespace App\Models\Rooms;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'starts_on', 'ends_on', 'room_type_id', 'percent', 'is_active'])]
class SeasonalDiscount extends Model
{
    use HasUuids;

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean', 'starts_on' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d', 'percent' => 'integer'];
    }
}
