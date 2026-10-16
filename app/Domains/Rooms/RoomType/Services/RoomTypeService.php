<?php

namespace App\Domains\Rooms\RoomType\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Rooms\RoomType\DTOs\CreateRoomTypeDTO;
use App\Domains\Rooms\RoomType\DTOs\UpdateRoomTypeDTO;
use App\Domains\Rooms\RoomType\Repositories\RoomTypeRepositoryInterface;
use App\Exceptions\EntityAlreadyExistsException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Rooms\RoomType;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomTypeService
{
    public function __construct(
        private RoomTypeRepositoryInterface $repository,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, RoomType> */
    public function list(bool $activeOnly = false): Collection
    {
        return $this->repository->all($activeOnly);
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): RoomType
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Room type', $id);
    }

    public function create(CreateRoomTypeDTO $dto): RoomType
    {
        $slug = Str::slug($dto->name);
        if ($this->repository->existsBySlug($slug)) {
            throw new EntityAlreadyExistsException('Room type', $dto->name);
        }

        return DB::transaction(function () use ($dto, $slug) {
            $type = $this->repository->create([...$dto->toArray(), 'slug' => $slug]);
            $this->audit->record('room_type.created', $type);

            return $type;
        });
    }

    public function update(string $id, UpdateRoomTypeDTO $dto): RoomType
    {
        $type = $this->find($id);
        $data = $dto->attributes;
        if ($dto->has('name')) {
            $data['slug'] = Str::slug($data['name']);
            if ($this->repository->existsBySlug($data['slug'], $type->id)) {
                throw new EntityAlreadyExistsException('Room type', $data['name']);
            }
        }
        $before = $type->attributesToArray();

        return DB::transaction(function () use ($type, $data, $before) {
            $updated = $this->repository->update($type, $data);
            $this->audit->record('room_type.updated', $updated, AuditLogger::diff($before, $data));

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $type = $this->find($id);
        if ($this->repository->hasRooms($type)) {
            throw new EntityValidationException("{$type->name} still has rooms. Move or delete them first.");
        }
        DB::transaction(function () use ($type) {
            $this->repository->delete($type);
            $this->audit->record('room_type.deleted', $type, ['name' => ['from' => $type->name, 'to' => null]]);
        });
    }
}
