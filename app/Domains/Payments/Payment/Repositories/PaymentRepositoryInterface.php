<?php

namespace App\Domains\Payments\Payment\Repositories;

use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

interface PaymentRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    /** @return LazyCollection<int, Payment> for CSV export */
    public function cursor(ListQuery $query): LazyCollection;

    public function findById(string $id): ?Payment;

    /** @param array<string, mixed> $data */
    public function create(array $data): Payment;

    /** @param array<string, mixed> $data */
    public function update(Payment $payment, array $data): Payment;

    public function refundedAmount(Payment $payment): int;

    /** Payment requests still waiting for the guest, on a booking or a facility booking. */
    public function pendingFor(Reservation|FacilityBooking $owner): Collection;

    /** @return array{received: array{count: int, total: int}, waiting: array{count: int, total: int}, failed: array{count: int, total: int}, unmatched: int} */
    public function summary(string $from, string $to): array;
}
