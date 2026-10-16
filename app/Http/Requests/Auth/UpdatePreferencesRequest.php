<?php

namespace App\Http\Requests\Auth;

use App\Domains\Users\User\DTOs\PreferencesDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', Rule::in(PreferencesDTO::MODES)],
            'accent' => ['sometimes', Rule::in(PreferencesDTO::ACCENTS)],
            'sidebar_collapsed' => ['sometimes', 'boolean'],
            'table_density' => ['sometimes', Rule::in(PreferencesDTO::DENSITIES)],
        ];
    }
}
