<?php

namespace App\Domains\Guests\Guest\Repositories;

use App\Models\Guests\Guest;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GuestRepositoryInterface
{
    public function paginate(ListQuery $query): LengthAwarePaginator;

    public function findById(string $id): ?Guest;

    public function findByPhone(string $phone): ?Guest;

    /** Same phone and same email (any case): safe proof it is the same person. */
    public function findByPhoneAndEmail(string $phone, string $email): ?Guest;

    public function hasReservations(Guest $guest): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): Guest;

    /** @param array<string, mixed> $data */
    public function update(Guest $guest, array $data): Guest;

    public function delete(Guest $guest): void;
}
