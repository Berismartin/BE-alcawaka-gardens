<?php

namespace App\Http\Requests\Staff;

use App\Domains\Users\User\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'role' => ['required', Rule::enum(UserRole::class)->except([UserRole::Guest])],
        ];
    }
}
