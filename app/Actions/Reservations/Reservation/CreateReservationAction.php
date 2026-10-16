<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\DTOs\CreateReservationDTO;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Reservations\ReservationResource;

class CreateReservationAction
{
    public function __construct(private ReservationService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): ReservationResource
    {
        return new ReservationResource($this->service->create(CreateReservationDTO::fromRequest($input)));
    }
}
