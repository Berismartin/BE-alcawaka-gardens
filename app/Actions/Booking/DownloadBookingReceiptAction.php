<?php

namespace App\Actions\Booking;

use App\Domains\Reservations\Reservation\Services\OnlineBookingService;
use Illuminate\Http\Response;

class DownloadBookingReceiptAction
{
    public function __construct(private OnlineBookingService $service) {}

    public function execute(string $reference, string $key): Response
    {
        $receipt = $this->service->receipt($reference, $key);

        return response($receipt->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->filename().'"',
        ]);
    }
}
