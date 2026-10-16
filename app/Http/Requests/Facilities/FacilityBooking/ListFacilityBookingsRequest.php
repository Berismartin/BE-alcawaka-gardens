<?php

namespace App\Http\Requests\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListFacilityBookingsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['starts_at', 'party_size', 'amount_ugx'];
    }

    protected function filters(): array
    {
        return [
            'facility_id' => ['uuid'],
            'status' => [Rule::enum(FacilityBookingStatus::class)],
        ];
    }
}
