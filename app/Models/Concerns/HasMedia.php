<?php

namespace App\Models\Concerns;

use App\Domains\Media\Media\Services\MediaService;
use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** For models that own uploads: files are removed from disk when the model is deleted. */
trait HasMedia
{
    public static function bootHasMedia(): void
    {
        static::deleting(fn ($model) => app(MediaService::class)->deleteAllFor($model));
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }
}
