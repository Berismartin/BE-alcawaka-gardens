<?php

namespace App\Http\Requests\Facilities\Facility;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFacilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'access' => ['sometimes', 'required', Rule::enum(FacilityAccess::class)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'opening_hours' => ['sometimes', 'nullable', 'string', 'max:120'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'price_ugx' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
