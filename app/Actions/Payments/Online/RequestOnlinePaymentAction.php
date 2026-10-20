<?php

namespace App\Actions\Payments\Online;

use App\Domains\Facilities\FacilityBooking\Services\FacilityBookingService;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Services\OnlinePaymentService;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Http\Resources\Payments\PaymentResource;

/** Staff take a Pesapal payment at the desk or POS: the guest pays on Pesapal's page on that screen. */
class RequestOnlinePaymentAction
{
    public function __construct(
        private OnlinePaymentService $online,
        private ReservationService $reservations,
        private FacilityBookingService $facilityBookings,
    ) {}

    /** @param array{amount_ugx: int, method: string} $input */
    public function execute(string $ownerType, string $id, array $input): PaymentResource
    {
        $owner = $ownerType === 'reservation' ? $this->reservations->find($id) : $this->facilityBookings->find($id);

        return new PaymentResource($this->online->request($owner, PaymentMethod::from($input['method']), (int) $input['amount_ugx'], '/payment/done'));
    }
}
