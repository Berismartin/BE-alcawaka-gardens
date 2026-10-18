<?php

namespace App\Actions\Booking\Facility;

use App\Domains\Facilities\FacilityBooking\DTOs\OnlineFacilityBookingDTO;
use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Http\Resources\Booking\PublicFacilityBookingResource;

class BookFacilityOnlineAction
{
    public function __construct(private FacilityBookingService $service) {}

    /** @param array<string, mixed> $input */
    public function execute(array $input): PublicFacilityBookingResource
    {
        ['booking' => $booking, 'key' => $key] = $this->service->bookOnline(OnlineFacilityBookingDTO::fromRequest($input));

        return new PublicFacilityBookingResource($booking->load('facility.photos'), $key);
    }
}
