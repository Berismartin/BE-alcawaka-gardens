<?php

namespace App\Http\Resources\Rooms;

use App\Http\Resources\Media\MediaResource;
use App\Models\Rooms\RoomType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoomType */
class RoomTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'base_rate_ugx' => $this->base_rate_ugx,
            'max_guests' => $this->max_guests,
            'bed_type' => $this->bed_type,
            'features' => $this->features ?? [],
            'is_active' => $this->is_active,
            'rooms_count' => $this->whenCounted('rooms'),
            'photos' => MediaResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
