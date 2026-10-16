<?php

namespace App\Http\Resources\Users;

use App\Domains\Users\User\DTOs\PreferencesDTO;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'permissions' => $this->role->permissions(),
            'has_pin' => $this->pin_hash !== null,
            'preferences' => PreferencesDTO::resolve($this->preferences),
        ];
    }
}
