<?php

namespace App\Domains\Reports\Report\Repositories;

use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Models\Rooms\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportRepository implements ReportRepositoryInterface
{
    /** @return list<string> */
    private static function holding(): array
    {
        return array_values(array_map(fn ($s) => $s->value, array_filter(ReservationStatus::cases(), fn ($s) => $s->holdsRoom())));
    }

    public function roomCount(): int
    {
        return Room::count();
    }

    public function soldRoomNights(string $from, string $to): int
    {
        return DB::table('reservation_nights')
            ->join('reservations', 'reservations.id', '=', 'reservation_nights.reservation_id')
            ->whereIn('reservations.status', self::holding())
            ->whereBetween('reservation_nights.night', [$from, $to])
            ->count();
    }

    public function payments(string $fromUtc, string $toUtc): Collection
    {
        return DB::table('payments')
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereBetween('received_at', [$fromUtc, $toUtc])
            ->get(['received_at', 'kind', 'amount_ugx']);
    }

    public function chargesBySource(string $from, string $to): Collection
    {
        return DB::table('folio_charges')
            ->join('reservations', 'reservations.id', '=', 'folio_charges.reservation_id')
            ->whereNotIn('reservations.status', [ReservationStatus::Cancelled->value, ReservationStatus::Expired->value])
            ->whereBetween('folio_charges.posted_on', [$from, $to])
            ->groupBy('reservations.source', 'folio_charges.category')
            ->get(['reservations.source', 'folio_charges.category', DB::raw('sum(folio_charges.amount_ugx) as total')]);
    }

    public function facilityRevenue(string $fromUtc, string $toUtc): int
    {
        return (int) DB::table('facility_bookings')
            ->whereIn('charge_mode', [ChargeMode::Paid->value, ChargeMode::PayLater->value])
            ->where('status', '!=', FacilityBookingStatus::Cancelled->value)
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->sum('amount_ugx');
    }

    public function openBalances(): array
    {
        $paid = DB::table('payments')
            ->selectRaw("reservation_id, sum(case when kind = 'refund' then -amount_ugx else amount_ugx end) as paid")
            ->where('status', PaymentStatus::Succeeded->value)
            ->whereNotNull('reservation_id')
            ->groupBy('reservation_id');
        $charged = DB::table('folio_charges')->selectRaw('reservation_id, sum(amount_ugx) as charged')->groupBy('reservation_id');

        $row = DB::table('reservations')
            ->joinSub($charged, 'c', 'c.reservation_id', '=', 'reservations.id')
            ->leftJoinSub($paid, 'p', 'p.reservation_id', '=', 'reservations.id')
            ->whereIn('reservations.status', [ReservationStatus::PendingPayment->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])
            ->whereRaw('c.charged - coalesce(p.paid, 0) > 0')
            ->selectRaw('count(*) as n, coalesce(sum(c.charged - coalesce(p.paid, 0)), 0) as total')
            ->first();

        return ['count' => (int) $row->n, 'total' => (int) $row->total];
    }

    public function bookingsCreated(string $fromUtc, string $toUtc): int
    {
        return DB::table('reservations')
            ->whereNotIn('status', [ReservationStatus::Expired->value])
            ->whereBetween('created_at', [$fromUtc, $toUtc])
            ->count();
    }
}
