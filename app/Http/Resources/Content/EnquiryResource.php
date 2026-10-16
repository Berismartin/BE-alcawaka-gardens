<?php

namespace App\Http\Resources\Content;

use App\Models\Content\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Enquiry */
class EnquiryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'event_type' => $this->event_type,
            'event_date' => $this->event_date?->format('Y-m-d'),
            'guests' => $this->guests,
            'message' => $this->message,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
