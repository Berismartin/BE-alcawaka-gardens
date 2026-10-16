<?php

namespace App\Domains\Rooms\Room\Repositories;

use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class RoomRepository implements RoomRepositoryInterface
{
    use AppliesListQuery;

    private const WITH = ['type', 'assignee:id,name', 'changedBy:id,name'];

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(Room::query()->with(self::WITH), $query, ['number', 'status_note'], defaultSort: 'number');
    }

    public function all(): Collection
    {
        return Room::with(self::WITH)->orderBy('number')->get();
    }

    public function findById(string $id): ?Room
    {
        return Room::with(self::WITH)->find($id);
    }

    public function existsByNumber(string $number, ?string $exceptId = null): bool
    {
        return Room::where('number', $number)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function hasReservations(Room $room): bool
    {
        return Reservation::where('room_id', $room->id)->exists();
    }

    public function create(array $data): Room
    {
        return Room::create($data)->load(self::WITH);
    }

    public function update(Room $room, array $data): Room
    {
        $room->update($data);

        return $room->refresh()->load(self::WITH);
    }

    public function delete(Room $room): void
    {
        $room->delete();
    }

    public function countByStatus(): array
    {
        return Room::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)->all();
    }
}
