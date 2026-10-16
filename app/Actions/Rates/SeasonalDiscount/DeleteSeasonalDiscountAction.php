<?php

namespace App\Actions\Rates\SeasonalDiscount;

use App\Domains\Rates\SeasonalDiscount\Services\SeasonalDiscountService;

class DeleteSeasonalDiscountAction
{
    public function __construct(private SeasonalDiscountService $service) {}

    public function execute(string $id): void
    {
        $this->service->delete($id);
    }
}
