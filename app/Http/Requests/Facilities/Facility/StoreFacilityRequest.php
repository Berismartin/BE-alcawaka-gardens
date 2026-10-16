<?php

namespace App\Http\Requests\Facilities\Facility;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'access' => ['required', Rule::enum(FacilityAccess::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'opening_hours' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'price_ugx' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
