<?php

namespace App\Http\Requests\Facilities\Facility;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListFacilitiesRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['name', 'capacity', 'price_ugx', 'created_at'];
    }

    protected function filters(): array
    {
        return [
            'access' => [Rule::enum(FacilityAccess::class)],
            'is_active' => [Rule::in(['true', 'false', '1', '0'])],
        ];
    }
}
