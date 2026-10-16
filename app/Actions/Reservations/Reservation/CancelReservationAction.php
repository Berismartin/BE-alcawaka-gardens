<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Reservations\ReservationResource;

class CancelReservationAction
{
    public function __construct(private ReservationService $service) {}

    public function execute(string $id, string $reason): ReservationResource
    {
        return new ReservationResource($this->service->cancel($id, $reason));
    }
}
