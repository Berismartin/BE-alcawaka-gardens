<?php

namespace App\Http\Requests\Reservations;

use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListReservationsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['check_in', 'check_out', 'number', 'created_at'];
    }

    protected function filters(): array
    {
        return [
            'status' => [Rule::enum(ReservationStatus::class)],
            'source' => [Rule::enum(ReservationSource::class)],
            'room_type_id' => ['uuid'],
            'room_id' => ['uuid'],
            'guest_id' => ['uuid'],
        ];
    }
}
