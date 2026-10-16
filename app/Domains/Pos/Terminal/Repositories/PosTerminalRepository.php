<?php

namespace App\Domains\Pos\Terminal\Repositories;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Pos\PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PosTerminalRepository implements PosTerminalRepositoryInterface
{
    public function all(): Collection
    {
        return PosTerminal::orderBy('name')->get();
    }

    public function findById(string $id): ?PosTerminal
    {
        return PosTerminal::find($id);
    }

    public function findBySecretHash(string $hash): ?PosTerminal
    {
        return PosTerminal::where('secret_hash', $hash)->first();
    }

    public function create(array $data): PosTerminal
    {
        return PosTerminal::create($data);
    }

    public function touch(PosTerminal $terminal): void
    {
        $terminal->forceFill(['last_used_at' => now()])->saveQuietly();
    }

    public function delete(PosTerminal $terminal): void
    {
        DB::table('personal_access_tokens')->where('name', 'pos:'.$terminal->id)->delete(); // sign everyone off it
        $terminal->delete();
    }

    public function staffWithPin(): Collection
    {
        return User::query()
            ->where('role', '!=', UserRole::Guest->value)
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }
}
