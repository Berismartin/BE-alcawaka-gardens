<?php

namespace App\Http\Requests\Rates;

use Illuminate\Foundation\Http\FormRequest;

/** Create and update both send the full discount. */
class SeasonalDiscountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'percent' => ['required', 'integer', 'min:1', 'max:90'],
            'room_type_id' => ['nullable', 'uuid', 'exists:room_types,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
