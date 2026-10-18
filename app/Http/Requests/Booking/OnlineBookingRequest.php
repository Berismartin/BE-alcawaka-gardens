<?php

namespace App\Http\Requests\Booking;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnlineBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'room_type_id' => ['required', 'uuid', 'exists:room_types,id'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'guest' => ['required', 'array'],
            'guest.full_name' => ['required', 'string', 'max:120'],
            'guest.phone' => ['required', 'string', 'max:25'],
            'guest.email' => ['required', 'email', 'max:160'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'special_requests' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::in(PaymentMethod::onlineChoices())],
        ];
    }

    public function attributes(): array
    {
        return ['guest.full_name' => 'name', 'guest.phone' => 'phone', 'guest.email' => 'email', 'payment_method' => 'payment method'];
    }
}
