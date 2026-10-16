<?php

namespace App\Actions\Rates\SeasonalDiscount;

use App\Domains\Rates\SeasonalDiscount\Services\SeasonalDiscountService;
use App\Http\Resources\Rates\SeasonalDiscountResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListSeasonalDiscountsAction
{
    public function __construct(private SeasonalDiscountService $service) {}

    public function execute(): AnonymousResourceCollection
    {
        return SeasonalDiscountResource::collection($this->service->list());
    }
}
