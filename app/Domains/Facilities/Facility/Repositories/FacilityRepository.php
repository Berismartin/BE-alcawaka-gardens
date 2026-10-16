<?php

namespace App\Domains\Facilities\Facility\Repositories;

use App\Models\Facilities\Facility;
use Illuminate\Database\Eloquent\Collection;

class FacilityRepository implements FacilityRepositoryInterface
{
    public function all(bool $activeOnly = false): Collection
    {
        return Facility::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function findById(string $id): ?Facility
    {
        return Facility::find($id);
    }

    public function existsBySlug(string $slug, ?string $exceptId = null): bool
    {
        return Facility::where('slug', $slug)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    public function create(array $data): Facility
    {
        return Facility::create($data);
    }

    public function update(Facility $facility, array $data): Facility
    {
        $facility->update($data);

        return $facility->refresh();
    }

    public function delete(Facility $facility): void
    {
        $facility->delete();
    }
}
