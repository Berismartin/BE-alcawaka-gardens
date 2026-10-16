<?php

namespace App\Actions\Settings;

use App\Domains\Settings\Setting\Services\SettingsService;

class UpdateSettingsAction
{
    public function __construct(private SettingsService $service) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function execute(array $input): array
    {
        return $this->service->update($input);
    }
}
