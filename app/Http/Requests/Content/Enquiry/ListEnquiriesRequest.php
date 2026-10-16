<?php

namespace App\Http\Requests\Content\Enquiry;

use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListEnquiriesRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['created_at', 'event_date', 'name'];
    }

    protected function filters(): array
    {
        return ['status' => [Rule::enum(EnquiryStatus::class)]];
    }
}
