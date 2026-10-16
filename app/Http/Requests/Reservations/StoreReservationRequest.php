<?php

namespace App\Http\Requests\Reservations;

use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Front-desk booking: walk-in or phone, with an optional payment taken on the spot. */
class StoreReservationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'source' => ['sometimes', Rule::in([ReservationSource::FrontDesk->value, ReservationSource::Phone->value])],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'guest_id' => ['nullable', 'uuid', 'exists:guests,id'],
            'guest' => ['required_without:guest_id', 'array'],
            'guest.full_name' => ['required_without:guest_id', 'string', 'max:120'],
            'guest.phone' => ['nullable', 'string', 'max:25'],
            'guest.email' => ['nullable', 'email', 'max:160'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::enum(PaymentMethod::class)],
            'payment.amount_ugx' => ['required_with:payment', 'integer', 'min:1'],
            'payment.reference' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return ['guest.full_name' => 'guest name', 'guest.phone' => 'phone', 'guest.email' => 'email', 'payment.amount_ugx' => 'amount'];
    }
}
