<?php

namespace App\Http\Resources\Rooms;

use App\Models\Rooms\Room;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Room */
class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'floor' => $this->floor,
            'room_type_id' => $this->room_type_id,
            'type' => $this->whenLoaded('type', fn () => [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'base_rate_ugx' => $this->type->base_rate_ugx,
                'bed_type' => $this->type->bed_type,
                'max_guests' => $this->type->max_guests,
            ]),
            'status' => $this->status->value,
            'status_note' => $this->status_note,
            'status_changed_at' => $this->status_changed_at?->toISOString(),
            'status_changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? ['id' => $this->changedBy->id, 'name' => $this->changedBy->name] : null),
            'assigned_to' => $this->whenLoaded('assignee', fn () => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name] : null),
        ];
    }
}
