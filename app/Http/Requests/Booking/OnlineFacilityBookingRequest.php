<?php

namespace App\Http\Requests\Booking;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnlineFacilityBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'uuid', 'exists:facilities,id'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+1 year'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:500'],
            'guest' => ['required', 'array'],
            'guest.full_name' => ['required', 'string', 'max:120'],
            'guest.phone' => ['required', 'string', 'max:20'],
            'guest.email' => ['required', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:200'],
            'payment_method' => ['required', Rule::in(PaymentMethod::onlineChoices())],
        ];
    }

    public function attributes(): array
    {
        return ['guest.full_name' => 'name', 'guest.phone' => 'phone', 'guest.email' => 'email', 'party_size' => 'number of guests', 'payment_method' => 'payment method'];
    }
}
