<?php

namespace App\Domains\Users\User\Repositories;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\User;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    use AppliesListQuery;

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function savePreferences(User $user, array $preferences): User
    {
        $user->forceFill(['preferences' => $preferences])->save();

        return $user;
    }

    public function paginateStaff(ListQuery $query): LengthAwarePaginator
    {
        $base = User::query()->where('role', '!=', UserRole::Guest->value);

        return $this->paginateList($base, $query, ['name', 'email'], defaultSort: 'name');
    }

    public function create(array $data): User
    {
        $user = new User;
        $user->forceFill($data)->save();

        return $user->refresh();
    }

    public function update(User $user, array $data): User
    {
        $user->forceFill($data)->save();

        return $user->refresh();
    }

    public function revokeTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    public function countActiveWithRole(string $role): int
    {
        return User::where('role', $role)->where('is_active', true)->count();
    }

    public function activeStaff(): Collection
    {
        return User::where('role', '!=', UserRole::Guest->value)->where('is_active', true)->whereNull('invitation_token')->get();
    }
}
