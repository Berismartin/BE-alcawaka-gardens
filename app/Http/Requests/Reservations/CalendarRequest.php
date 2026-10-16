<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'days' => ['required', 'integer', Rule::in([7, 14, 30])],
        ];
    }
}
