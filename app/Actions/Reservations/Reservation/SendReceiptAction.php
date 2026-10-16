<?php

namespace App\Actions\Reservations\Reservation;

use App\Domains\Reservations\Reservation\Services\FolioService;

class SendReceiptAction
{
    public function __construct(private FolioService $service) {}

    /** @return string the address it was sent to */
    public function execute(string $id, ?string $email): string
    {
        return $this->service->sendReceipt($id, $email);
    }
}
