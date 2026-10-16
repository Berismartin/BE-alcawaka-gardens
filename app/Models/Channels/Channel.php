<?php

namespace App\Models\Channels;

use App\Models\Rooms\Room;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'room_id', 'import_url', 'export_token', 'is_active', 'last_synced_at', 'last_sync_status', 'last_sync_message'])]
#[Hidden(['export_token'])]
class Channel extends Model
{
    use HasUuids;

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_synced_at' => 'datetime'];
    }
}
