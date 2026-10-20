<?php

namespace App\Http\Requests\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'uuid', 'exists:facilities,id'],
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d H:i', 'after:starts_at'],
            'party_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'charge_mode' => ['required', Rule::enum(ChargeMode::class)],
            'reservation_id' => ['nullable', 'uuid', 'exists:reservations,id'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_phone' => ['nullable', 'string', 'max:25'],
            'amount_ugx' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
