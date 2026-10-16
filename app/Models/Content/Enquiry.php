<?php

namespace App\Models\Content;

use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'email', 'event_type', 'event_date', 'guests', 'message', 'status', 'notes'])]
class Enquiry extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'event_date' => 'date:Y-m-d',
            'guests' => 'integer',
        ];
    }
}
