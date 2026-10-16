<?php

namespace App\Actions\Staff;

use App\Domains\Users\User\Services\StaffService;
use App\Http\Resources\Users\StaffResource;

class ResendInviteAction
{
    public function __construct(private StaffService $service) {}

    public function execute(int $id): StaffResource
    {
        return new StaffResource($this->service->resendInvite($id));
    }
}
