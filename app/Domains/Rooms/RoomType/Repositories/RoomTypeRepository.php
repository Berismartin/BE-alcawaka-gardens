<?php

namespace App\Domains\Rooms\RoomType\Repositories;

use App\Models\Rooms\RoomType;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RoomTypeRepository implements RoomTypeRepositoryInterface
{
    use AppliesListQuery;

    public function all(bool $activeOnly = false): Collection
    {
        return RoomType::query()
            ->with('photos')
            ->withCount('rooms')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('base_rate_ugx')
            ->get();
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(RoomType::query()->with('photos')->withCount('rooms'), $query, ['name', 'description'], defaultSort: 'base_rate_ugx');
    }

    public function findById(string $id): ?RoomType
    {
        return RoomType::with('photos')->withCount('rooms')->find($id);
    }

    public function existsBySlug(string $slug, ?string $exceptId = null): bool
    {
        return RoomType::where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function hasRooms(RoomType $type): bool
    {
        return $type->rooms()->exists();
    }

    public function create(array $data): RoomType
    {
        return RoomType::create($data)->loadCount('rooms')->load('photos');
    }

    public function update(RoomType $type, array $data): RoomType
    {
        $type->update($data);

        return $type->refresh()->loadCount('rooms')->load('photos');
    }

    public function delete(RoomType $type): void
    {
        $type->delete();
    }
}
