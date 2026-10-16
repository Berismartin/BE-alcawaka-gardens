<?php

namespace App\Actions\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Http\Resources\Facilities\FacilityBooking\FacilityBookingResource;

class PayFacilityBookingAction
{
    public function __construct(private FacilityBookingService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(string $id, array $input): FacilityBookingResource
    {
        return new FacilityBookingResource($this->service->recordPayment($id, RecordPaymentDTO::fromRequest($input)));
    }
}
