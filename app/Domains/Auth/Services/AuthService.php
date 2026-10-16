<?php

namespace App\Domains\Auth\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Auth\DTOs\LoginDTO;
use App\Domains\Auth\DTOs\SetPasswordDTO;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Domains\Users\User\Services\StaffService;
use App\Exceptions\EntityValidationException;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    /** @return array{user: User, token: string} */
    public function login(LoginDTO $dto): array
    {
        $user = $this->users->findByEmail($dto->email);

        // Same message for unknown email and wrong password, so emails can't be probed.
        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw new EntityValidationException('These credentials do not match our records.', [
                'email' => ['These credentials do not match our records.'],
            ]);
        }
        if (! $user->is_active) {
            throw new EntityValidationException('This account has been deactivated. Ask your manager to restore it.', [
                'email' => ['This account has been deactivated.'],
            ]);
        }

        return $this->issueToken($user, $dto->deviceName);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    /** @return array{user: User, token: string} signs the new staff member straight in */
    public function acceptInvite(SetPasswordDTO $dto): array
    {
        $user = $this->users->findByEmail($dto->email);
        $valid = $user
            && $user->isInvitePending()
            && hash_equals($user->invitation_token, hash('sha256', $dto->token))
            && $user->invited_at?->gt(now()->subDays(StaffService::INVITE_DAYS));
        if (! $valid) {
            throw new EntityValidationException('This invitation link is invalid or has expired. Ask your manager to send a new one.', [
                'token' => ['This invitation link is invalid or has expired.'],
            ]);
        }

        $user = $this->users->update($user, ['password' => $dto->password, 'invitation_token' => null, 'email_verified_at' => now()]);
        $this->audit->record('staff.invite_accepted', $user);
        $this->notifications->notify('staff.manage', new PortalNotification("{$user->name} joined the portal", 'They accepted the invitation and set a password.', '/portal/staff', 'i-lucide-user-check'));

        return $this->issueToken($user, 'portal');
    }

    /** Always succeeds from the caller's view, so it can't be used to find accounts. */
    public function sendResetLink(string $email): void
    {
        $user = $this->users->findByEmail(strtolower(trim($email)));
        if ($user && $user->is_active && ! $user->isInvitePending()) {
            Password::sendResetLink(['email' => $user->email]);
        }
    }

    public function resetPassword(SetPasswordDTO $dto): void
    {
        $status = Password::reset(
            ['email' => $dto->email, 'token' => $dto->token, 'password' => $dto->password],
            function (User $user, string $password) {
                $this->users->update($user, ['password' => $password]);
                $this->users->revokeTokens($user);
                $this->audit->record('auth.password_reset', $user);
            },
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw new EntityValidationException('This reset link is invalid or has expired. Request a new one.', [
                'token' => ['This reset link is invalid or has expired.'],
            ]);
        }
    }

    /** @return array{user: User, token: string} */
    private function issueToken(User $user, string $device): array
    {
        $this->users->update($user, ['last_active_at' => now()]);

        return ['user' => $user, 'token' => $user->createToken($device)->plainTextToken];
    }
}
