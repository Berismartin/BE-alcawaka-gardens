<?php

namespace App\Http\Resources\Facilities\Facility;

use App\Http\Resources\Media\MediaResource;
use App\Models\Facilities\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facility */
class FacilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'opening_hours' => $this->opening_hours,
            'capacity' => $this->capacity,
            'access' => $this->access->value,
            'price_ugx' => $this->price_ugx,
            'is_active' => $this->is_active,
            'photos' => MediaResource::collection($this->whenLoaded('photos')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
