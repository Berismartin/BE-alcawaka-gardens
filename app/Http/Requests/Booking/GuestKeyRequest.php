<?php

namespace App\Http\Requests\Booking;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Every guest action on a booking carries its key; some carry a bit more. */
class GuestKeyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:100'],
            'payment_method' => [Rule::requiredIf($this->routeIs('booking.pay', 'facility-bookings.pay')), Rule::in(PaymentMethod::onlineChoices())],
            'outcome' => [Rule::requiredIf($this->routeIs('booking.simulate', 'facility-bookings.simulate')), Rule::in(['approve', 'decline'])],
            'type' => [Rule::requiredIf($this->routeIs('booking.request')), Rule::in(['change', 'cancel'])],
            'new_check_in' => ['required_if:type,change', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'new_check_out' => ['required_if:type,change', 'nullable', 'date_format:Y-m-d', 'after:new_check_in'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
