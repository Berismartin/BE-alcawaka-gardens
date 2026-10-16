<?php

namespace App\Domains\Rooms\Room\Repositories;

use App\Models\Rooms\Room;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RoomRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    /** @return Collection<int, Room> every room with its type, ordered by number */
    public function all(): Collection;

    public function findById(string $id): ?Room;

    public function existsByNumber(string $number, ?string $exceptId = null): bool;

    public function hasReservations(Room $room): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): Room;

    /** @param array<string, mixed> $data */
    public function update(Room $room, array $data): Room;

    public function delete(Room $room): void;

    /** @return array<string, int> status => count */
    public function countByStatus(): array;
}
