<?php

namespace App\Domains\Rooms\Room\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Rooms\Room\DTOs\ChangeRoomStatusDTO;
use App\Domains\Rooms\Room\DTOs\CreateRoomDTO;
use App\Domains\Rooms\Room\DTOs\UpdateRoomDTO;
use App\Domains\Rooms\Room\Enums\RoomStatus;
use App\Domains\Rooms\Room\Repositories\RoomRepositoryInterface;
use App\Exceptions\EntityAlreadyExistsException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Rooms\Room;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoomService
{
    public function __construct(
        private RoomRepositoryInterface $repository,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    /** @return Collection<int, Room> */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function find(string $id): Room
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Room', $id);
    }

    public function create(CreateRoomDTO $dto): Room
    {
        if ($this->repository->existsByNumber($dto->number)) {
            throw new EntityAlreadyExistsException('Room', $dto->number);
        }

        return DB::transaction(function () use ($dto) {
            $room = $this->repository->create([...$dto->toArray(), 'status_changed_at' => now()]);
            $this->audit->record('room.created', $room);

            return $room;
        });
    }

    public function update(string $id, UpdateRoomDTO $dto): Room
    {
        $room = $this->find($id);
        if ($dto->has('number') && $this->repository->existsByNumber($dto->attributes['number'], $room->id)) {
            throw new EntityAlreadyExistsException('Room', $dto->attributes['number']);
        }
        $before = $room->attributesToArray();

        return DB::transaction(function () use ($room, $dto, $before) {
            $updated = $this->repository->update($room, $dto->attributes);
            $this->audit->record('room.updated', $updated, AuditLogger::diff($before, $dto->attributes));

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $room = $this->find($id);
        if ($this->repository->hasReservations($room)) {
            throw new EntityValidationException("Room {$room->number} has bookings, so it can't be deleted. Set it out of order instead.");
        }
        DB::transaction(function () use ($room) {
            $this->repository->delete($room);
            $this->audit->record('room.deleted', $room, ['number' => ['from' => $room->number, 'to' => null]]);
        });
    }

    /** Housekeeping: mark clean, report an issue, mark ready. "Occupied" only comes from check-in. */
    public function changeStatus(string $id, ChangeRoomStatusDTO $dto): Room
    {
        $room = $this->find($id);
        if ($dto->status === RoomStatus::Occupied) {
            throw new EntityValidationException('Rooms become occupied when a guest checks in.', ['status' => ['Rooms become occupied when a guest checks in.']]);
        }
        if ($room->status === RoomStatus::Occupied) {
            throw new EntityValidationException("Room {$room->number} is occupied. Its status changes when the guest checks out.");
        }
        if (in_array($dto->status, [RoomStatus::UnderMaintenance, RoomStatus::OutOfOrder], true) && ! $dto->note) {
            throw new EntityValidationException('Describe the issue so maintenance knows what to fix.', ['note' => ['Describe the issue so maintenance knows what to fix.']]);
        }

        $data = ['status' => $dto->status->value, 'status_note' => $dto->note];
        if ($dto->assigneeSent) {
            $data['assigned_to'] = $dto->assignedTo;
        }

        $wasDirty = $room->status === RoomStatus::NeedsCleaning;

        return DB::transaction(function () use ($room, $data, $dto, $wasDirty) {
            $updated = $this->setStatus($room, $data);
            if ($dto->status === RoomStatus::Available && $wasDirty) {
                $this->notifications->notify('reservations.manage', new PortalNotification("Room {$room->number} is clean and ready", null, '/portal/housekeeping', 'i-lucide-sparkles'));
            }
            if ($dto->status->blocksBooking()) {
                $this->notifications->notify('rooms.manage', new PortalNotification("Room {$room->number}: {$dto->status->label()}", $dto->note, '/portal/housekeeping', 'i-lucide-wrench'));
            }

            return $updated;
        });
    }

    /** Used by check-in and check-out. */
    public function markForStay(Room $room, RoomStatus $status): Room
    {
        return $this->setStatus($room, ['status' => $status->value, 'status_note' => null]);
    }

    /** @return array<string, int> */
    public function countByStatus(): array
    {
        return $this->repository->countByStatus();
    }

    /** @param array<string, mixed> $data */
    private function setStatus(Room $room, array $data): Room
    {
        $from = $room->status->value;
        $updated = $this->repository->update($room, [...$data, 'status_changed_at' => now(), 'status_changed_by' => auth()->id()]);
        $this->audit->record('room.status_changed', $updated, ['status' => ['from' => $from, 'to' => $data['status']]]);

        return $updated;
    }
}
