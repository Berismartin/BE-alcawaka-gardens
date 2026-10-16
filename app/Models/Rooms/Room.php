<?php

namespace App\Models\Rooms;

use App\Domains\Rooms\Room\Enums\RoomStatus;
use App\Models\User;
use Database\Factories\Rooms\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['number', 'floor', 'room_type_id', 'status', 'status_note', 'assigned_to', 'status_changed_at', 'status_changed_by'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, HasUuids;

    protected static function newFactory(): RoomFactory
    {
        return RoomFactory::new();
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'floor' => 'integer',
            'status_changed_at' => 'datetime',
        ];
    }
}
