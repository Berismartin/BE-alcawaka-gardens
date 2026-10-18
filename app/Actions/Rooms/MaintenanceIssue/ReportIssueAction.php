<?php

namespace App\Actions\Rooms\MaintenanceIssue;

use App\Domains\Rooms\MaintenanceIssue\DTOs\ReportIssueDTO;
use App\Domains\Rooms\MaintenanceIssue\Services\MaintenanceService;
use App\Http\Resources\Rooms\MaintenanceIssueResource;

class ReportIssueAction
{
    public function __construct(private MaintenanceService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): MaintenanceIssueResource
    {
        return new MaintenanceIssueResource($this->service->report(ReportIssueDTO::fromRequest($input)));
    }
}
