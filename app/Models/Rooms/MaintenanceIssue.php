<?php

namespace App\Models\Rooms;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_id', 'description', 'closes_room', 'reported_by', 'resolved_at', 'resolved_by', 'resolution'])]
class MaintenanceIssue extends Model
{
    use HasUuids;

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    protected function casts(): array
    {
        return [
            'closes_room' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }
}
