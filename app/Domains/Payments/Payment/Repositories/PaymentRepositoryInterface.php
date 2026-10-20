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

    /** Re-read the row and lock it until the transaction ends, so two settlements can't both apply. */
    public function findForUpdate(string $id): ?Payment;

    public function findByTrackingId(string $trackingId): ?Payment;

    /**
     * Online payments still waiting on their gateway, oldest first.
     *
     * @return Collection<int, Payment>
     */
    public function pendingAtGateway(\DateTimeInterface $createdBefore): Collection;

    /**
     * Refunds asked of the gateway and not yet confirmed.
     *
     * @return Collection<int, Payment>
     */
    public function pendingGatewayRefunds(): Collection;

    /** Pesapal allows a single refund per payment: has one been asked for (pending or done)? */
    public function hasRefundRequest(Payment $payment): bool;

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
