<?php

namespace App\Domains\Users\User\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Users\User\DTOs\InviteStaffDTO;
use App\Domains\Users\User\DTOs\UpdateStaffDTO;
use App\Domains\Users\User\Enums\UserRole;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Exceptions\EntityAlreadyExistsException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Mail\StaffInvitationMail;
use App\Models\User;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Staff accounts (B12): invite by email, change role, deactivate. */
class StaffService
{
    public const INVITE_DAYS = 7;

    public function __construct(
        private UserRepositoryInterface $users,
        private AuditLogger $audit,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->users->paginateStaff($query);
    }

    public function find(int $id): User
    {
        $user = $this->users->findById($id);
        if (! $user || ! $user->role->isStaff()) {
            throw new EntityNotFoundException('Staff member', (string) $id);
        }

        return $user;
    }

    public function invite(InviteStaffDTO $dto): User
    {
        $this->assertStaffRole($dto->role);
        if ($this->users->findByEmail($dto->email)) {
            throw new EntityAlreadyExistsException('An account', $dto->email);
        }

        return DB::transaction(function () use ($dto) {
            $user = $this->users->create([
                'name' => $dto->name,
                'email' => $dto->email,
                'role' => $dto->role,
                'password' => Str::random(40), // unusable until the invite is accepted
                'is_active' => true,
            ]);
            $this->sendInvite($user);
            $this->audit->record('staff.invited', $user, ['role' => ['from' => null, 'to' => $dto->role->value]]);

            return $user;
        });
    }

    public function resendInvite(int $id): User
    {
        $user = $this->find($id);
        if (! $user->isInvitePending()) {
            throw new EntityValidationException("{$user->name} has already set a password.");
        }
        $this->sendInvite($user);
        $this->audit->record('staff.invite_resent', $user);

        return $user->refresh();
    }

    public function update(int $id, UpdateStaffDTO $dto, User $actor): User
    {
        $user = $this->find($id);
        $data = $dto->attributes;

        if ($user->is($actor) && ($dto->has('role') || ($dto->has('is_active') && ! $data['is_active']))) {
            throw new EntityValidationException("You can't change your own role or deactivate yourself. Ask another manager.");
        }
        if ($dto->has('role')) {
            $data['role'] = UserRole::from($data['role']);
            $this->assertStaffRole($data['role']);
        }
        $losesManager = $user->role === UserRole::Manager && $user->is_active
            && (($dto->has('role') && $data['role'] !== UserRole::Manager) || ($dto->has('is_active') && ! $data['is_active']));
        if ($losesManager && $this->users->countActiveWithRole(UserRole::Manager->value) <= 1) {
            throw new EntityValidationException('The hotel needs at least one active manager.');
        }

        $before = ['name' => $user->name, 'role' => $user->role->value, 'is_active' => $user->is_active];

        return DB::transaction(function () use ($user, $data, $before) {
            $updated = $this->users->update($user, $data);
            if (! $updated->is_active || ($before['role'] !== $updated->role->value)) {
                $this->users->revokeTokens($updated); // sign them out everywhere; the new role applies at next sign-in
            }
            $after = ['name' => $updated->name, 'role' => $updated->role->value, 'is_active' => $updated->is_active];
            $this->audit->record('staff.updated', $updated, AuditLogger::diff($before, $after));

            return $updated;
        });
    }

    private function sendInvite(User $user): void
    {
        $token = Str::random(48);
        $this->users->update($user, ['invitation_token' => hash('sha256', $token), 'invited_at' => now()]);
        $url = rtrim(config('app.frontend_url'), '/').'/portal/accept-invite?'.http_build_query(['email' => $user->email, 'token' => $token]);
        Mail::to($user->email)->send(new StaffInvitationMail($user, $url, self::INVITE_DAYS));
    }

    private function assertStaffRole(UserRole $role): void
    {
        if (! $role->isStaff()) {
            throw new EntityValidationException('Choose a staff role.', ['role' => ['Choose a staff role.']]);
        }
    }
}
