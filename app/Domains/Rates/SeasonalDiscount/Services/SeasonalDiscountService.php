<?php

namespace App\Domains\Rates\SeasonalDiscount\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Rates\SeasonalDiscount\DTOs\SeasonalDiscountDTO;
use App\Domains\Rates\SeasonalDiscount\Repositories\SeasonalDiscountRepositoryInterface;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Rooms\SeasonalDiscount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SeasonalDiscountService
{
    public function __construct(
        private SeasonalDiscountRepositoryInterface $repository,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, SeasonalDiscount> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(string $id): SeasonalDiscount
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Discount', $id);
    }

    public function create(SeasonalDiscountDTO $dto): SeasonalDiscount
    {
        $this->assertNoOverlap($dto);

        return DB::transaction(function () use ($dto) {
            $discount = $this->repository->create($dto->toArray());
            $this->audit->record('discount.created', $discount);

            return $discount;
        });
    }

    public function update(string $id, SeasonalDiscountDTO $dto): SeasonalDiscount
    {
        $discount = $this->find($id);
        $this->assertNoOverlap($dto, $discount->id, $dto->isActive ?? $discount->is_active);
        $before = $discount->attributesToArray();

        return DB::transaction(function () use ($discount, $dto, $before) {
            $updated = $this->repository->update($discount, $dto->toArray());
            $this->audit->record('discount.updated', $updated, AuditLogger::diff($before, $dto->toArray()));

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $discount = $this->find($id);
        DB::transaction(function () use ($discount) {
            $this->repository->delete($discount);
            $this->audit->record('discount.deleted', $discount, ['name' => ['from' => $discount->name, 'to' => null]]);
        });
    }

    /** Two active discounts on the same night for the same room would make the price ambiguous. */
    private function assertNoOverlap(SeasonalDiscountDTO $dto, ?string $exceptId = null, ?bool $active = null): void
    {
        if (! ($active ?? $dto->isActive ?? true)) {
            return; // switched off: can share dates with anything until it is switched back on
        }
        $clash = $this->repository->overlapping($dto->startsOn, $dto->endsOn, $dto->roomTypeId, $exceptId)->first();
        if ($clash) {
            $message = "These dates overlap “{$clash->name}” ({$clash->starts_on->format('j M')} – {$clash->ends_on->format('j M Y')}).";
            throw new EntityValidationException($message, ['starts_on' => [$message]]);
        }
    }
}
