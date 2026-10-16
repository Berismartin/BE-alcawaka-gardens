<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomTypeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'base_rate_ugx' => ['required', 'integer', 'min:0', 'max:100000000'],
            'max_guests' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'bed_type' => ['nullable', 'string', 'max:60'],
            'features' => ['sometimes', 'array', 'max:20'],
            'features.*' => ['string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
