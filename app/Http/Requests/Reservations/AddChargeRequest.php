<?php

namespace App\Http\Requests\Reservations;

use App\Domains\Reservations\Reservation\Enums\ChargeCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddChargeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(ChargeCategory::class)->except([ChargeCategory::Room])],
            'description' => ['required', 'string', 'max:255'],
            'amount_ugx' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}
