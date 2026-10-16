<?php

namespace App\Actions\Rates\SeasonalDiscount;

use App\Domains\Rates\SeasonalDiscount\DTOs\SeasonalDiscountDTO;
use App\Domains\Rates\SeasonalDiscount\Services\SeasonalDiscountService;
use App\Http\Resources\Rates\SeasonalDiscountResource;

class UpdateSeasonalDiscountAction
{
    public function __construct(private SeasonalDiscountService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): SeasonalDiscountResource
    {
        return new SeasonalDiscountResource($this->service->update($id, SeasonalDiscountDTO::fromRequest($input)));
    }
}
