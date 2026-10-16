<?php

namespace App\Models\Facilities;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Models\Concerns\HasMedia;
use Database\Factories\Facilities\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'slug', 'description', 'opening_hours', 'capacity', 'access', 'price_ugx', 'is_active'])]
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected static function newFactory(): FacilityFactory
    {
        return FacilityFactory::new();
    }

    /** Gallery, cover first. */
    public function photos(): MorphMany
    {
        return $this->media()->where('collection', 'facility_photos');
    }

    protected function casts(): array
    {
        return [
            'access' => FacilityAccess::class,
            'capacity' => 'integer',
            'price_ugx' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
