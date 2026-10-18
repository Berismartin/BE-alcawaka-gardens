<?php

namespace App\Http\Resources\Rooms;

use App\Models\Rooms\MaintenanceIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceIssue */
class MaintenanceIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'room' => ['id' => $this->room->id, 'number' => $this->room->number, 'status' => $this->room->status->value, 'type' => $this->room->type?->name],
            'description' => $this->description,
            'closes_room' => $this->closes_room,
            'reported_by' => $this->reporter?->name,
            'created_at' => $this->created_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
            'resolved_by' => $this->resolver?->name,
            'resolution' => $this->resolution,
        ];
    }
}
