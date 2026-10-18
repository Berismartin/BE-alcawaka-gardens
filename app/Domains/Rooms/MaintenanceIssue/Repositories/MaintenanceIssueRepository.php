<?php

namespace App\Domains\Rooms\MaintenanceIssue\Repositories;

use App\Models\Rooms\MaintenanceIssue;
use App\Models\Rooms\Room;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaintenanceIssueRepository implements MaintenanceIssueRepositoryInterface
{
    use AppliesListQuery;

    private const WITH = ['room:id,number,status,room_type_id', 'room.type:id,name', 'reporter:id,name', 'resolver:id,name'];

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        // "state" (open or fixed) is not a column: apply it here and pass the rest on.
        $filters = $query->filters;
        $state = $filters['state'] ?? null;
        unset($filters['state']);
        $query = new ListQuery($query->page, $query->perPage, $query->search, $query->sort, $filters, $query->from, $query->to);
        $builder = MaintenanceIssue::query()->with(self::WITH)
            ->when($state === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($state === 'fixed', fn ($q) => $q->whereNotNull('resolved_at'));

        return $this->paginateList($builder, $query, ['description', 'resolution'], defaultSort: '-created_at');
    }

    public function findById(string $id): ?MaintenanceIssue
    {
        return MaintenanceIssue::with(self::WITH)->find($id);
    }

    public function create(array $data): MaintenanceIssue
    {
        return MaintenanceIssue::create($data)->load(self::WITH);
    }

    public function update(MaintenanceIssue $issue, array $data): MaintenanceIssue
    {
        $issue->update($data);

        return $issue->refresh()->load(self::WITH);
    }

    public function latestOpen(Room $room): ?MaintenanceIssue
    {
        return MaintenanceIssue::with(self::WITH)->where('room_id', $room->id)->whereNull('resolved_at')->latest()->latest('id')->first();
    }

    public function openClosingCount(Room $room): int
    {
        return MaintenanceIssue::where('room_id', $room->id)->where('closes_room', true)->whereNull('resolved_at')->count();
    }

    public function resolveClosing(Room $room, ?int $userId, string $resolution): void
    {
        MaintenanceIssue::where('room_id', $room->id)->where('closes_room', true)->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by' => $userId, 'resolution' => $resolution]);
    }
}
