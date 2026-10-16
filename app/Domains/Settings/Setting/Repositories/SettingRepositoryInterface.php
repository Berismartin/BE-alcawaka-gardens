<?php

namespace App\Domains\Settings\Setting\Repositories;

interface SettingRepositoryInterface
{
    /** @return array<string, mixed> */
    public function all(): array;

    /** @param array<string, mixed> $values */
    public function putMany(array $values): void;
}
