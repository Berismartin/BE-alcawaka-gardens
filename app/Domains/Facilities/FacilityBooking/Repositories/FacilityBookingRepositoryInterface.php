<?php

namespace App\Domains\Facilities\FacilityBooking\Repositories;

use App\Models\Facilities\FacilityBooking;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FacilityBookingRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?FacilityBooking;

    /** @param array<string, mixed> $data */
    public function create(array $data): FacilityBooking;

    /** @param array<string, mixed> $data */
    public function update(FacilityBooking $booking, array $data): FacilityBooking;

    /** @return array<string, mixed> KPIs for the facilities page (B14) */
    public function summary(string $dayStart, string $dayEnd, string $weekEnd): array;
}
