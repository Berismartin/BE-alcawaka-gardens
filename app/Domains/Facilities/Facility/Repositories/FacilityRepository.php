<?php

namespace App\Domains\Facilities\Facility\Repositories;

use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityBooking;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FacilityRepository implements FacilityRepositoryInterface
{
    use AppliesListQuery;

    public function all(bool $activeOnly = false): Collection
    {
        return Facility::query()
            ->with('photos')
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(Facility::query()->with('photos'), $query, ['name', 'description'], defaultSort: 'name');
    }

    public function findById(string $id): ?Facility
    {
        return Facility::with('photos')->find($id);
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

    public function hasBookings(Facility $facility): bool
    {
        return FacilityBooking::where('facility_id', $facility->id)->exists();
    }
}
