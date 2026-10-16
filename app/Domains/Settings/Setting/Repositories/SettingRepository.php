<?php

namespace App\Domains\Settings\Setting\Repositories;

use App\Models\Settings\Setting;

class SettingRepository implements SettingRepositoryInterface
{
    public function all(): array
    {
        return Setting::query()->pluck('value', 'key')->all();
    }

    public function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
