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
            // A guest's change or cancel request from "My booking": staff can apply it in one click.
            'booking_request' => $this->request_type ? [
                'type' => $this->request_type,
                'reservation_id' => $this->reservation_id,
                'reference' => $this->reservation?->reference(),
                'reservation_status' => $this->reservation?->status->value,
                'check_in' => $this->requested_check_in,
                'check_out' => $this->requested_check_out,
            ] : null,
            'status' => $this->status->value,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
