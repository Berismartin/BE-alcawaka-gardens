<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\FolioService;
use App\Http\Resources\Reservations\ReservationResource;

class RemoveChargeAction
{
    public function __construct(private FolioService $service) {}

    public function execute(string $id, string $chargeId): ReservationResource
    {
        return new ReservationResource($this->service->removeCharge($id, $chargeId));
    }
}
