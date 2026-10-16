<?php

namespace App\Domains\Reports\Report\Repositories;

use Illuminate\Support\Collection;

/** Read-only queries behind the dashboard and reports. Dates are hotel-local Y-m-d strings. */
interface ReportRepositoryInterface
{
    public function roomCount(): int;

    /** Room-nights sold (any room-holding status) for nights in [from, to]. */
    public function soldRoomNights(string $from, string $to): int;

    /** @return Collection<int, object{received_at: string, kind: string, amount_ugx: int}> succeeded payments with received_at in [fromUtc, toUtc] */
    public function payments(string $fromUtc, string $toUtc): Collection;

    /** @return Collection<int, object{source: string, category: string, total: int}> folio charges posted in [from, to] grouped by booking source and category */
    public function chargesBySource(string $from, string $to): Collection;

    /** Direct-paid facility bookings (not on a room bill) starting in [fromUtc, toUtc]. */
    public function facilityRevenue(string $fromUtc, string $toUtc): int;

    /** @return array{count: int, total: int} open bills that still owe money */
    public function openBalances(): array;

    public function bookingsCreated(string $fromUtc, string $toUtc): int;
}
