<?php

namespace App\Http\Resources\Content;

use App\Models\Content\ContentEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContentEntry */
class ContentEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'body' => $this->body,
            'image_url' => $this->image_url,
            'data' => (object) ($this->data ?? []), // always a JSON object, never []
            'sort_order' => $this->sort_order,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
