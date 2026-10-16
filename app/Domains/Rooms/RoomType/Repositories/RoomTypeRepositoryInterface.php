<?php

namespace App\Domains\Rooms\RoomType\Repositories;

use App\Models\Rooms\RoomType;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface RoomTypeRepositoryInterface
{
    /** @return Collection<int, RoomType> */
    public function all(bool $activeOnly = false): Collection;

    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?RoomType;

    public function existsBySlug(string $slug, ?string $exceptId = null): bool;

    public function hasRooms(RoomType $type): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): RoomType;

    /** @param array<string, mixed> $data */
    public function update(RoomType $type, array $data): RoomType;

    public function delete(RoomType $type): void;
}
