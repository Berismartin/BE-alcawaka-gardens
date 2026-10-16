<?php

namespace App\Actions\Facilities\FacilityBooking;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;

class FacilityBookingSummaryAction
{
    public function __construct(private FacilityBookingService $service) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $summary = $this->service->summary();
        $event = $summary['next_event'];
        $summary['next_event'] = $event ? ['facility' => $event->facility->name, 'starts_at' => $event->starts_at->toISOString(), 'party_size' => $event->party_size] : null;

        return $summary;
    }
}
