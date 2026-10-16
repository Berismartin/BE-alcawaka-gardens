<?php

namespace App\Domains\Facilities\Facility\Repositories;

use App\Models\Facilities\Facility;
use Illuminate\Database\Eloquent\Collection;

interface FacilityRepositoryInterface
{
    /** @return Collection<int, Facility> */
    public function all(bool $activeOnly = false): Collection;

    public function findById(string $id): ?Facility;

    public function existsBySlug(string $slug, ?string $exceptId = null): bool;

    /** @param array<string, mixed> $data */
    public function create(array $data): Facility;

    /** @param array<string, mixed> $data */
    public function update(Facility $facility, array $data): Facility;

    public function delete(Facility $facility): void;
}
