<?php

namespace App\Http\Requests\Guests;

use App\Http\Requests\ListRequest;

class ListGuestsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['full_name', 'created_at', 'reservations_count', 'last_stay_on'];
    }
}
