<?php

namespace App\Http\Requests\Staff;

use App\Domains\Users\User\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)->except([UserRole::Guest])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
