<?php

namespace App\Actions\Payments\Online;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Payments\PaymentLink\Services\PaymentLinkService;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Payments\PaymentLinkResource;

class SendPaymentLinkAction
{
    public function __construct(
        private PaymentLinkService $links,
        private ReservationService $reservations,
        private FacilityBookingService $facilityBookings,
    ) {}

    /** @param array{email: string, amount_ugx?: ?int, message?: ?string} $input */
    public function execute(string $ownerType, string $id, array $input): PaymentLinkResource
    {
        $owner = $ownerType === 'reservation' ? $this->reservations->find($id) : $this->facilityBookings->find($id);
        $sent = $this->links->send($owner, $input['email'], isset($input['amount_ugx']) ? (int) $input['amount_ugx'] : null, $input['message'] ?? null);

        return new PaymentLinkResource($sent['link'], $sent['emailed']);
    }
}
