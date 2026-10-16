<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Reservations\Reservation\Services\FolioService;
use App\Http\Resources\Reservations\ReservationResource;

class RecordPaymentAction
{
    public function __construct(private FolioService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): ReservationResource
    {
        return new ReservationResource($this->service->recordPayment($id, RecordPaymentDTO::fromRequest($input)));
    }
}
