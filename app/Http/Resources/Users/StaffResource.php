<?php

namespace App\Http\Resources\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'invite_pending' => $this->isInvitePending(),
            'has_pin' => $this->pin_hash !== null,
            'invited_at' => $this->invited_at?->toISOString(),
            'last_active_at' => $this->last_active_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
