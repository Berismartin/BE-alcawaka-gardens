<?php

namespace App\Domains\Reservations\Reservation\Repositories;

use App\Models\Reservations\FolioCharge;
use App\Models\Reservations\Reservation;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ReservationRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?Reservation;

    public function nextNumber(): int;

    /** @return list<string> room ids with at least one night taken in [checkIn, checkOut) */
    public function takenRoomIds(string $checkIn, string $checkOut, ?string $exceptReservationId = null): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): Reservation;

    /** @param array<string, mixed> $data */
    public function update(Reservation $reservation, array $data): Reservation;

    /**
     * Writes one row per night. Throws UniqueConstraintViolationException when a night is taken.
     *
     * @param  array<string, int>  $rates  night => rate
     */
    public function insertNights(Reservation $reservation, string $roomId, array $rates): void;

    /** Frees the room: removes nights on or after $from (all nights when null). */
    public function releaseNights(Reservation $reservation, ?string $from = null): void;

    /** @param array<string, mixed> $data */
    public function addCharge(Reservation $reservation, array $data): FolioCharge;

    public function findCharge(Reservation $reservation, string $chargeId): ?FolioCharge;

    public function deleteCharge(FolioCharge $charge): void;

    /** @return Collection<int, Reservation> stays holding a room on any night in [from, to) */
    public function inRange(string $from, string $to): Collection;

    /** @return Collection<int, Reservation> */
    public function arrivalsOn(string $date): Collection;

    /** @return Collection<int, Reservation> */
    public function departuresOn(string $date): Collection;

    /** @return Collection<int, Reservation> unpaid holds whose time ran out */
    public function expiredHolds(): Collection;

    /** @return Collection<int, Reservation> */
    public function search(string $term, int $limit): Collection;
}
