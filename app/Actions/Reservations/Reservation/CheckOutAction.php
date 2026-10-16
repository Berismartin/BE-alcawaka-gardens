<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Reservations\ReservationResource;

class CheckOutAction
{
    public function __construct(private ReservationService $service) {}

    public function execute(string $id): ReservationResource
    {
        return new ReservationResource($this->service->checkOut($id));
    }
}
