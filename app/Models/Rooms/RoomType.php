<?php

namespace App\Models\Rooms;

use App\Models\Concerns\HasMedia;
use Database\Factories\Rooms\RoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'slug', 'description', 'base_rate_ugx', 'max_guests', 'bed_type', 'features', 'is_active'])]
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected static function newFactory(): RoomTypeFactory
    {
        return RoomTypeFactory::new();
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class)->orderBy('number');
    }

    /** Gallery, cover first. */
    public function photos(): MorphMany
    {
        return $this->media()->where('collection', 'room_photos');
    }

    protected function casts(): array
    {
        return [
            'base_rate_ugx' => 'integer',
            'max_guests' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
