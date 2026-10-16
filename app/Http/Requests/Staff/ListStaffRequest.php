<?php

namespace App\Http\Requests\Staff;

use App\Domains\Users\User\Enums\UserRole;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListStaffRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['name', 'last_active_at', 'created_at'];
    }

    protected function filters(): array
    {
        return [
            'role' => [Rule::enum(UserRole::class)],
            'is_active' => [Rule::in(['true', 'false', '1', '0'])],
        ];
    }
}
