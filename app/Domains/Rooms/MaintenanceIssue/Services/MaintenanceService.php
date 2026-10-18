<?php

namespace App\Domains\Rooms\MaintenanceIssue\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Rooms\MaintenanceIssue\DTOs\ReportIssueDTO;
use App\Domains\Rooms\MaintenanceIssue\Repositories\MaintenanceIssueRepositoryInterface;
use App\Domains\Rooms\Room\DTOs\ChangeRoomStatusDTO;
use App\Domains\Rooms\Room\Enums\RoomStatus;
use App\Domains\Rooms\Room\Services\RoomService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Rooms\MaintenanceIssue;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Broken things in rooms. An issue either closes the room (through the room status, which
 * records the issue itself) or leaves it in use, like a dripping tap in an occupied room.
 */
class MaintenanceService
{
    public function __construct(
        private MaintenanceIssueRepositoryInterface $repository,
        private RoomService $rooms,
        private NotificationService $notifications,
        private AuditLogger $audit,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function report(ReportIssueDTO $dto): MaintenanceIssue
    {
        $room = $this->rooms->find($dto->roomId);
        if ($dto->closeAs) {
            if (! $dto->closeAs->blocksBooking()) {
                throw new EntityValidationException('Choose under maintenance or out of order.', ['close_as' => ['Choose under maintenance or out of order.']]);
            }
            $this->rooms->changeStatus($room->id, new ChangeRoomStatusDTO($dto->closeAs, $dto->description));

            return $this->repository->latestOpen($room); // the status change recorded it
        }

        return DB::transaction(function () use ($room, $dto) {
            $issue = $this->repository->create(['room_id' => $room->id, 'description' => $dto->description, 'closes_room' => false, 'reported_by' => auth()->id()]);
            $this->audit->record('room.issue_reported', $room, ['issue' => ['from' => null, 'to' => $dto->description]]);
            $this->notifications->notify('housekeeping.manage', new PortalNotification("Room {$room->number}: {$dto->description}", 'Reported. The room stays in use.', '/portal/maintenance', 'i-lucide-wrench'));

            return $issue;
        });
    }

    /** Fixed. When it was the last issue keeping the room closed, the room goes to housekeeping for a check. */
    public function resolve(string $id, ?string $resolution): MaintenanceIssue
    {
        $issue = $this->repository->findById($id) ?? throw new EntityNotFoundException('Maintenance issue', $id);
        if (! $issue->isOpen()) {
            throw new EntityValidationException('This issue is already marked fixed.');
        }

        return DB::transaction(function () use ($issue, $resolution) {
            $updated = $this->repository->update($issue, ['resolved_at' => now(), 'resolved_by' => auth()->id(), 'resolution' => $resolution]);
            $room = $issue->room;
            $this->audit->record('room.issue_fixed', $room, ['issue' => ['from' => $issue->description, 'to' => null]]);
            if ($issue->closes_room && $room->status->blocksBooking() && $this->repository->openClosingCount($room) === 0) {
                $this->rooms->changeStatus($room->id, new ChangeRoomStatusDTO(RoomStatus::NeedsCleaning));
            }

            return $updated->refresh()->load(['room.type:id,name', 'reporter:id,name', 'resolver:id,name']);
        });
    }
}
