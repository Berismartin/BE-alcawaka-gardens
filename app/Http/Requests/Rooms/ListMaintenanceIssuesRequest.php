<?php

namespace App\Http\Requests\Rooms;

use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListMaintenanceIssuesRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['created_at', 'resolved_at'];
    }

    protected function filters(): array
    {
        return [
            'state' => [Rule::in(['open', 'fixed'])],
            'room_id' => ['uuid'],
        ];
    }
}
