<?php

namespace App\Actions\Reports;

use App\Domains\Reports\Report\Services\ReportService;

class ReportAction
{
    public function __construct(private ReportService $service) {}

    /** @return array<string, mixed> */
    public function execute(string $period, string $date): array
    {
        return $this->service->report($period, $date);
    }
}
