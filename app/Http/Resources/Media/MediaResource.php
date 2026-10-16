<?php

namespace App\Http\Resources\Media;

use App\Models\Media\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** @mixin Media */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection,
            // Private files are only reachable through a short-lived signed link.
            'url' => $this->isPrivate()
                ? URL::temporarySignedRoute('media.file', now()->addMinutes(config('media.private_link_minutes')), ['id' => $this->id])
                : Storage::disk($this->disk)->url($this->path),
            'visibility' => $this->visibility,
            'mime' => $this->mime,
            'size' => $this->size,
            'original_name' => $this->original_name,
            'width' => $this->width,
            'height' => $this->height,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
