<?php

namespace App\Domains\Users\User\Repositories;

use App\Models\User;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;

    /** @param array<string, mixed> $preferences */
    public function savePreferences(User $user, array $preferences): User;

    public function paginateStaff(ListQuery $query): LengthAwarePaginator;

    /** @param array<string, mixed> $data trusted fields only (role is not mass-assignable) */
    public function create(array $data): User;

    /** @param array<string, mixed> $data trusted fields only */
    public function update(User $user, array $data): User;

    public function revokeTokens(User $user): void;

    public function countActiveWithRole(string $role): int;

    /** @return Collection<int, User> active staff who hold any of the abilities */
    public function activeStaff(): Collection;
}
