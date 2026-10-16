<?php

namespace App\Domains\Rates\SeasonalDiscount\Repositories;

use App\Models\Rooms\SeasonalDiscount;
use Illuminate\Database\Eloquent\Collection;

class SeasonalDiscountRepository implements SeasonalDiscountRepositoryInterface
{
    public function all(): Collection
    {
        return SeasonalDiscount::with('roomType:id,name')->orderBy('starts_on')->get();
    }

    public function overlapping(string $from, string $to, ?string $roomTypeId, ?string $exceptId = null): Collection
    {
        return SeasonalDiscount::query()
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            // An "all rooms" discount applies to every type, so it collides with all of them.
            ->when($roomTypeId, fn ($q) => $q->where(fn ($w) => $w->whereNull('room_type_id')->orWhere('room_type_id', $roomTypeId)))
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->get();
    }

    public function findById(string $id): ?SeasonalDiscount
    {
        return SeasonalDiscount::with('roomType:id,name')->find($id);
    }

    public function create(array $data): SeasonalDiscount
    {
        return SeasonalDiscount::create($data)->load('roomType:id,name');
    }

    public function update(SeasonalDiscount $discount, array $data): SeasonalDiscount
    {
        $discount->update($data);

        return $discount->refresh()->load('roomType:id,name');
    }

    public function delete(SeasonalDiscount $discount): void
    {
        $discount->delete();
    }
}
