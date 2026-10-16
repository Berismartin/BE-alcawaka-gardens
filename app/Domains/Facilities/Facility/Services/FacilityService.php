<?php

namespace App\Domains\Facilities\Facility\Services;

use App\Domains\Facilities\Facility\DTOs\CreateFacilityDTO;
use App\Domains\Facilities\Facility\DTOs\UpdateFacilityDTO;
use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Domains\Facilities\Facility\Repositories\FacilityRepositoryInterface;
use App\Exceptions\EntityAlreadyExistsException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Facilities\Facility;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FacilityService
{
    public function __construct(private FacilityRepositoryInterface $repository) {}

    /** @return Collection<int, Facility> */
    public function list(bool $activeOnly = false): Collection
    {
        return $this->repository->all($activeOnly);
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): Facility
    {
        return $this->repository->findById($id)
            ?? throw new EntityNotFoundException('Facility', $id);
    }

    public function create(CreateFacilityDTO $dto): Facility
    {
        $slug = Str::slug($dto->name);
        if ($this->repository->existsBySlug($slug)) {
            throw new EntityAlreadyExistsException('Facility', $dto->name);
        }
        $this->assertPriceRule($dto->access, $dto->priceUgx);

        return DB::transaction(fn () => $this->repository->create([...$dto->toArray(), 'slug' => $slug]));
    }

    public function update(string $id, UpdateFacilityDTO $dto): Facility
    {
        $facility = $this->find($id);
        $data = $dto->attributes;

        if ($dto->has('name')) {
            $data['slug'] = Str::slug($data['name']);
            if ($this->repository->existsBySlug($data['slug'], $facility->id)) {
                throw new EntityAlreadyExistsException('Facility', $data['name']);
            }
        }

        $access = $dto->has('access') ? FacilityAccess::from($data['access']) : $facility->access;
        $price = $dto->has('price_ugx') ? $data['price_ugx'] : $facility->price_ugx;
        $this->assertPriceRule($access, $price);

        return DB::transaction(fn () => $this->repository->update($facility, $data));
    }

    public function delete(string $id): void
    {
        $this->repository->delete($this->find($id));
    }

    /** Paid and reservation-only facilities must carry a price; free ones may not. */
    private function assertPriceRule(FacilityAccess $access, ?int $price): void
    {
        if ($access->requiresPrice() && $price === null) {
            throw new EntityValidationException('A price is required for paid or reservation facilities.', [
                'price_ugx' => ['A price is required for paid or reservation facilities.'],
            ]);
        }
    }
}
