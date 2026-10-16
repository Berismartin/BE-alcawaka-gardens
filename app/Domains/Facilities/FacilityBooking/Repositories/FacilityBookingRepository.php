<?php

namespace App\Domains\Facilities\FacilityBooking\Repositories;

use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FacilityBookingRepository implements FacilityBookingRepositoryInterface
{
    use AppliesListQuery;

    private const WITH = ['facility:id,name,access', 'reservation:id,number,room_id', 'reservation.room:id,number', 'payments'];

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->paginateList(FacilityBooking::query()->with(self::WITH), $query, ['guest_name', 'guest_phone', 'notes'], dateColumn: 'starts_at', defaultSort: 'starts_at');
    }

    public function findById(string $id): ?FacilityBooking
    {
        return FacilityBooking::with(self::WITH)->find($id);
    }

    public function create(array $data): FacilityBooking
    {
        return FacilityBooking::create($data);
    }

    public function update(FacilityBooking $booking, array $data): FacilityBooking
    {
        $booking->update($data);

        return $booking->refresh()->load(self::WITH);
    }

    public function summary(string $dayStart, string $dayEnd, string $weekEnd): array
    {
        $live = FacilityBooking::query()->where('status', '!=', FacilityBookingStatus::Cancelled->value);
        $today = (clone $live)->whereBetween('starts_at', [$dayStart, $dayEnd]);
        $week = (clone $live)->whereBetween('starts_at', [$dayStart, $weekEnd]);

        return [
            'visitors_today' => (int) (clone $today)->sum('party_size'),
            'bookings_today' => (clone $today)->count(),
            'bookings_this_week' => $week->count(),
            'next_event' => (clone $live)->with('facility:id,name')->where('starts_at', '>=', $dayStart)->where('party_size', '>=', 10)->orderBy('starts_at')->first(),
            'income_today_ugx' => (int) Payment::query()->whereNotNull('facility_booking_id')
                ->where('status', PaymentStatus::Succeeded->value)
                ->whereBetween('received_at', [$dayStart, $dayEnd])
                ->sum('amount_ugx'),
            'pending_payments' => (clone $live)->where('status', FacilityBookingStatus::PendingPayment->value)->count(),
        ];
    }
}
