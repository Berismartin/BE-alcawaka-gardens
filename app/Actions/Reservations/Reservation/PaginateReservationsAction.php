<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginateReservationsAction
{
    public function __construct(private ReservationService $service) {}

    public function execute(ListQuery $query): LengthAwarePaginator
    {
        return $this->service->paginate($query);
    }
}
