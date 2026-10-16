<?php

namespace App\Domains\Rates\SeasonalDiscount\Services;

use App\Domains\Rates\SeasonalDiscount\Repositories\SeasonalDiscountRepositoryInterface;
use App\Models\Rooms\RoomType;
use Carbon\CarbonImmutable;

/** Nightly price = base rate less any seasonal discount for that night, in whole shillings. */
class PricingService
{
    public function __construct(private SeasonalDiscountRepositoryInterface $discounts) {}

    /** @return array<string, int> night (Y-m-d) => rate in UGX, one entry per night from check-in up to check-out */
    public function nightlyRates(RoomType $type, string $checkIn, string $checkOut): array
    {
        $from = CarbonImmutable::parse($checkIn);
        $lastNight = CarbonImmutable::parse($checkOut)->subDay();
        $discounts = $this->discounts->overlapping($from->toDateString(), $lastNight->toDateString(), $type->id);

        $rates = [];
        for ($night = $from; $night->lte($lastNight); $night = $night->addDay()) {
            $date = $night->toDateString();
            $percent = $discounts->first(fn ($d) => $d->starts_on->toDateString() <= $date && $d->ends_on->toDateString() >= $date)?->percent ?? 0;
            $rates[$date] = (int) round($type->base_rate_ugx * (100 - $percent) / 100);
        }

        return $rates;
    }
}
