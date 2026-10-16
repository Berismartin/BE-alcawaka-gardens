<?php

namespace App\Actions\Staff;

use App\Domains\Users\User\DTOs\UpdateStaffDTO;
use App\Domains\Users\User\Services\StaffService;
use App\Http\Resources\Users\StaffResource;
use App\Models\User;

class UpdateStaffAction
{
    public function __construct(private StaffService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(int $id, array $input, User $actor): StaffResource
    {
        return new StaffResource($this->service->update($id, UpdateStaffDTO::fromRequest($input), $actor));
    }
}
