<?php

namespace App\Actions\Reports;

use App\Domains\Reports\Report\Services\ReportService;
use App\Http\Resources\Reservations\ReservationResource;

class DashboardAction
{
    public function __construct(private ReportService $service) {}

    /** @return array<string, mixed> */
    public function execute(): array
    {
        $data = $this->service->dashboard();
        $data['arrivals'] = ReservationResource::collection($data['arrivals']);
        $data['departures'] = ReservationResource::collection($data['departures']);

        return $data;
    }
}
