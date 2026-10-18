<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\FolioService;
use Illuminate\Http\Response;

class DownloadReceiptAction
{
    public function __construct(private FolioService $service) {}

    public function execute(string $id): Response
    {
        $receipt = $this->service->receipt($id);

        return response($receipt->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->filename().'"',
        ]);
    }
}
