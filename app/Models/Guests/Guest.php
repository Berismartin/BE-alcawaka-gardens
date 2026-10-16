<?php

namespace App\Models\Guests;

use App\Models\Concerns\HasMedia;
use App\Models\Reservations\Reservation;
use Database\Factories\Guests\GuestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['full_name', 'phone', 'email', 'nationality', 'id_number', 'notes'])]
class Guest extends Model
{
    /** @use HasFactory<GuestFactory> */
    use HasFactory, HasMedia, HasUuids;

    protected static function newFactory(): GuestFactory
    {
        return GuestFactory::new();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class)->latest('check_in');
    }

    /** ID document scans (private files). */
    public function documents(): MorphMany
    {
        return $this->media()->where('collection', 'guest_id');
    }
}
