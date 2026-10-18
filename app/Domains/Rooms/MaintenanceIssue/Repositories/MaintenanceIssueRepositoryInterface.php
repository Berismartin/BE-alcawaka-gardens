<?php

namespace App\Domains\Rooms\MaintenanceIssue\Repositories;

use App\Models\Rooms\MaintenanceIssue;
use App\Models\Rooms\Room;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MaintenanceIssueRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?MaintenanceIssue;

    /** @param array<string, mixed> $data */
    public function create(array $data): MaintenanceIssue;

    /** @param array<string, mixed> $data */
    public function update(MaintenanceIssue $issue, array $data): MaintenanceIssue;

    public function latestOpen(Room $room): ?MaintenanceIssue;

    /** Open issues that keep this room closed for booking. */
    public function openClosingCount(Room $room): int;

    /** Marks every open issue that closed the room as fixed. */
    public function resolveClosing(Room $room, ?int $userId, string $resolution): void;
}
