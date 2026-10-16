<?php

namespace App\Domains\Rates\SeasonalDiscount\Repositories;

use App\Models\Rooms\SeasonalDiscount;
use Illuminate\Database\Eloquent\Collection;

interface SeasonalDiscountRepositoryInterface
{
    /** @return Collection<int, SeasonalDiscount> */
    public function all(): Collection;

    /** @return Collection<int, SeasonalDiscount> discounts touching [from, to] for a room type (or all rooms) */
    public function overlapping(string $from, string $to, ?string $roomTypeId, ?string $exceptId = null): Collection;

    public function findById(string $id): ?SeasonalDiscount;

    /** @param array<string, mixed> $data */
    public function create(array $data): SeasonalDiscount;

    /** @param array<string, mixed> $data */
    public function update(SeasonalDiscount $discount, array $data): SeasonalDiscount;

    public function delete(SeasonalDiscount $discount): void;
}
