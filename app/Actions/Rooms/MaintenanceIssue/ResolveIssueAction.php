<?php

namespace App\Actions\Rooms\MaintenanceIssue;

use App\Domains\Rooms\MaintenanceIssue\Services\MaintenanceService;
use App\Http\Resources\Rooms\MaintenanceIssueResource;

class ResolveIssueAction
{
    public function __construct(private MaintenanceService $service) {}

    public function execute(string $id, ?string $resolution): MaintenanceIssueResource
    {
        return new MaintenanceIssueResource($this->service->resolve($id, $resolution ? trim($resolution) : null));
    }
}
