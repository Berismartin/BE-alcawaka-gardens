<?php

namespace App\Actions\Staff;

use App\Domains\Users\User\DTOs\InviteStaffDTO;
use App\Domains\Users\User\Services\StaffService;
use App\Http\Resources\Users\StaffResource;

class InviteStaffAction
{
    public function __construct(private StaffService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): StaffResource
    {
        return new StaffResource($this->service->invite(InviteStaffDTO::fromRequest($input)));
    }
}
