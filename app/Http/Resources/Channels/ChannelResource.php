<?php

namespace App\Http\Resources\Channels;

use App\Models\Channels\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Channel */
class ChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'room' => ['id' => $this->room->id, 'number' => $this->room->number, 'type_name' => $this->room->type?->name],
            'import_url' => $this->import_url,
            // Give this link to the OTA so it can see which nights are taken.
            'export_url' => route('channels.feed', ['token' => $this->export_token]),
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at?->toISOString(),
            'last_sync_status' => $this->last_sync_status,
            'last_sync_message' => $this->last_sync_message,
        ];
    }
}
