<?php

namespace App\Actions\Users;

use App\Domains\Users\User\DTOs\PreferencesDTO;
use App\Domains\Users\User\Services\PreferencesService;
use App\Models\User;

class UpdatePreferencesAction
{
    public function __construct(private PreferencesService $service) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(User $user, array $input): array
    {
        return $this->service->update($user, PreferencesDTO::fromRequest($input));
    }
}
