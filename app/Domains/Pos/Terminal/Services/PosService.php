<?php

namespace App\Domains\Pos\Terminal\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Pos\Terminal\Repositories\PosTerminalRepositoryInterface;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Pos\PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Touch-screen POS: managers register a device as a terminal; on it, staff pick
 * their name and sign in with a PIN. A PIN never works anywhere else.
 */
class PosService
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 5;

    public const SESSION_HOURS = 12;

    public function __construct(
        private PosTerminalRepositoryInterface $terminals,
        private UserRepositoryInterface $users,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, PosTerminal> */
    public function terminals(): Collection
    {
        return $this->terminals->all();
    }

    /** @return array{terminal: PosTerminal, key: string} the key is shown once and kept on the device */
    public function registerTerminal(string $name): array
    {
        $key = Str::random(48);

        return DB::transaction(function () use ($name, $key) {
            $terminal = $this->terminals->create(['name' => $name, 'secret_hash' => hash('sha256', $key), 'created_by' => auth()->id()]);
            $this->audit->record('pos.terminal_registered', $terminal, ['name' => ['from' => null, 'to' => $name]]);

            return ['terminal' => $terminal, 'key' => $key];
        });
    }

    public function revokeTerminal(string $id): void
    {
        $terminal = $this->terminals->findById($id) ?? throw new EntityNotFoundException('Terminal', $id);
        DB::transaction(function () use ($terminal) {
            $this->terminals->delete($terminal);
            $this->audit->record('pos.terminal_revoked', $terminal, ['name' => ['from' => $terminal->name, 'to' => null]]);
        });
    }

    public function resolveTerminal(?string $key): ?PosTerminal
    {
        if (! $key) {
            return null;
        }
        $terminal = $this->terminals->findBySecretHash(hash('sha256', $key));
        if ($terminal) {
            $this->terminals->touch($terminal);
        }

        return $terminal;
    }

    /** @return Collection<int, User> */
    public function staffList(): Collection
    {
        return $this->terminals->staffWithPin();
    }

    /** @return array{user: User, token: string} */
    public function pinLogin(PosTerminal $terminal, int $userId, string $pin): array
    {
        $user = $this->users->findById($userId);
        if (! $user || ! $user->is_active || ! $user->role->isStaff() || ! $user->pin_hash) {
            throw new EntityValidationException('Pick your name again.', ['user_id' => ['This account can’t use the terminal.']]);
        }
        if ($user->pin_locked_until?->isFuture()) {
            $minutes = (int) ceil(now()->diffInSeconds($user->pin_locked_until) / 60);
            throw new EntityValidationException("Too many wrong PINs. Try again in {$minutes} min, or ask a manager.", ['pin' => ['Locked for now.']]);
        }
        if (! Hash::check($pin, $user->pin_hash)) {
            $attempts = $user->pin_failed_attempts + 1;
            $locked = $attempts >= self::MAX_ATTEMPTS;
            $this->users->update($user, [
                'pin_failed_attempts' => $locked ? 0 : $attempts,
                'pin_locked_until' => $locked ? now()->addMinutes(self::LOCK_MINUTES) : null,
            ]);
            if ($locked) {
                $this->audit->record('pos.pin_locked', $user, ['terminal' => ['from' => null, 'to' => $terminal->name]]);
            }
            $left = self::MAX_ATTEMPTS - $attempts;
            throw new EntityValidationException($locked ? 'Too many wrong PINs. Locked for '.self::LOCK_MINUTES.' minutes.' : "Wrong PIN. {$left} ".($left === 1 ? 'try' : 'tries').' left.', ['pin' => ['Wrong PIN.']]);
        }

        $this->users->update($user, ['pin_failed_attempts' => 0, 'pin_locked_until' => null, 'last_active_at' => now()]);
        $this->audit->record('pos.signed_in', $user, ['terminal' => ['from' => null, 'to' => $terminal->name]]);

        return ['user' => $user, 'token' => $user->createToken('pos:'.$terminal->id, ['*'], now()->addHours(self::SESSION_HOURS))->plainTextToken];
    }

    /** Staff set their own PIN, confirmed with their password. */
    public function setPin(User $user, string $pin, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new EntityValidationException('That password isn’t right.', ['current_password' => ['That password isn’t right.']]);
        }
        if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '12345', '123456', '4321', '0123'], true)) {
            throw new EntityValidationException('Pick a PIN that’s harder to guess.', ['pin' => ['Pick a PIN that’s harder to guess.']]);
        }
        $this->users->update($user, ['pin_hash' => Hash::make($pin), 'pin_failed_attempts' => 0, 'pin_locked_until' => null]);
        $this->audit->record('pos.pin_set', $user);
    }

    /** Manager: clear someone's PIN (forgotten or shared). */
    public function clearPin(int $userId): void
    {
        $user = $this->users->findById($userId) ?? throw new EntityNotFoundException('Staff member', (string) $userId);
        $this->users->update($user, ['pin_hash' => null, 'pin_failed_attempts' => 0, 'pin_locked_until' => null]);
        $this->audit->record('pos.pin_cleared', $user);
    }
}
