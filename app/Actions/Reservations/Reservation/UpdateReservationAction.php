<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\DTOs\UpdateReservationDTO;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Reservations\ReservationResource;

class UpdateReservationAction
{
    public function __construct(private ReservationService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): ReservationResource
    {
        return new ReservationResource($this->service->update($id, UpdateReservationDTO::fromRequest($input)));
    }
}
