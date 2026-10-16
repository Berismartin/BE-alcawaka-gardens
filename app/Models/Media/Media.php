<?php

namespace App\Models\Media;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['collection', 'disk', 'path', 'visibility', 'mime', 'size', 'original_name', 'width', 'height', 'mediable_type', 'mediable_id', 'sort_order', 'uploaded_by'])]
class Media extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'sort_order' => 'integer'];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }
}
