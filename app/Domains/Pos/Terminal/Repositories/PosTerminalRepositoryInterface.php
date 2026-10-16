<?php

namespace App\Domains\Pos\Terminal\Repositories;

use App\Models\Pos\PosTerminal;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface PosTerminalRepositoryInterface
{
    /** @return Collection<int, PosTerminal> */
    public function all(): Collection;

    public function findById(string $id): ?PosTerminal;

    public function findBySecretHash(string $hash): ?PosTerminal;

    /** @param array<string, mixed> $data */
    public function create(array $data): PosTerminal;

    public function touch(PosTerminal $terminal): void;

    public function delete(PosTerminal $terminal): void;

    /** @return Collection<int, User> active staff who have set a PIN */
    public function staffWithPin(): Collection;
}
