<?php

namespace App\Domains\Guests\Guest\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Guests\Guest\DTOs\GuestDTO;
use App\Domains\Guests\Guest\DTOs\UpdateGuestDTO;
use App\Domains\Guests\Guest\Repositories\GuestRepositoryInterface;
use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Guests\Guest;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GuestService
{
    public function __construct(
        private GuestRepositoryInterface $repository,
        private AuditLogger $audit,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): Guest
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Guest', $id);
    }

    public function create(GuestDTO $dto): Guest
    {
        $this->assertPhone($dto->phone);

        return DB::transaction(function () use ($dto) {
            $guest = $this->repository->create($dto->toArray());
            $this->audit->record('guest.created', $guest);

            return $this->find($guest->id);
        });
    }

    /** Bookings: a returning guest (same phone) keeps one record instead of a duplicate. */
    public function findOrCreate(GuestDTO $dto): Guest
    {
        $this->assertPhone($dto->phone);
        $existing = $dto->phone ? $this->repository->findByPhone($dto->phone) : null;

        return $existing ?? $this->create($dto);
    }

    public function update(string $id, UpdateGuestDTO $dto): Guest
    {
        $guest = $this->find($id);
        $this->assertPhone($dto->attributes['phone'] ?? null);
        $before = $guest->attributesToArray();

        return DB::transaction(function () use ($guest, $dto, $before) {
            $updated = $this->repository->update($guest, $dto->attributes);
            $this->audit->record('guest.updated', $updated, AuditLogger::diff($before, $dto->attributes));

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $guest = $this->find($id);
        if ($this->repository->hasReservations($guest)) {
            throw new EntityValidationException("{$guest->full_name} has bookings on record, so the guest can't be deleted.");
        }
        DB::transaction(function () use ($guest) {
            $this->repository->delete($guest);
            $this->audit->record('guest.deleted', $guest, ['full_name' => ['from' => $guest->full_name, 'to' => null]]);
        });
    }

    private function assertPhone(?string $phone): void
    {
        if (! PhoneNumber::isValid($phone)) {
            throw new EntityValidationException('Enter a valid phone number, for example +256 772 123 456.', [
                'phone' => ['Enter a valid phone number, for example +256 772 123 456.'],
            ]);
        }
    }
}
