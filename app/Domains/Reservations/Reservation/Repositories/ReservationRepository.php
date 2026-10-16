<?php

namespace App\Domains\Reservations\Reservation\Repositories;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Models\Reservations\FolioCharge;
use App\Models\Reservations\Reservation;
use App\Models\Reservations\ReservationNight;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ReservationRepository implements ReservationRepositoryInterface
{
    use AppliesListQuery;

    private const DETAIL = ['guest.documents', 'room.type', 'roomType', 'charges.creator:id,name', 'payments.recorder:id,name', 'creator:id,name', 'activity.user:id,name'];

    private const ROW = ['guest:id,full_name,phone,email', 'room:id,number,room_type_id,status', 'roomType:id,name'];

    /** Room-holding statuses as plain values, for whereIn. */
    private static function holding(): array
    {
        return array_map(fn ($s) => $s->value, array_filter(ReservationStatus::cases(), fn ($s) => $s->holdsRoom()));
    }

    /** Totals for list rows: charges and net paid without loading every row. */
    private function withTotals(Builder $query): Builder
    {
        return $query
            ->withSum('charges as charges_total', 'amount_ugx')
            ->addSelect(['paid_total' => DB::table('payments')
                ->selectRaw("coalesce(sum(case when kind = 'refund' then -amount_ugx else amount_ugx end), 0)")
                ->whereColumn('reservation_id', 'reservations.id')
                ->where('status', PaymentStatus::Succeeded->value)]);
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        $base = $this->withTotals(Reservation::query()->select('reservations.*')->with(self::ROW));

        // Search by guest name or phone, or by reference ("ARS-10482" or "10482").
        if ($query->search) {
            $term = $query->search;
            $number = (int) preg_replace('/\D/', '', $term);
            $base->where(fn (Builder $q) => $q
                ->whereHas('guest', fn ($g) => $g->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                ->when($number > 0, fn ($w) => $w->orWhere('number', $number)));
            $query = new ListQuery($query->page, $query->perPage, null, $query->sort, $query->filters, $query->from, $query->to);
        }

        // from/to are arrival days; check_in is a plain date, so compare it directly.
        $base->when($query->from, fn ($q) => $q->where('check_in', '>=', $query->from))
            ->when($query->to, fn ($q) => $q->where('check_in', '<=', $query->to));

        return $this->paginateList($base, $query, dateColumn: null, defaultSort: '-check_in');
    }

    public function findById(string $id): ?Reservation
    {
        return Reservation::with(self::DETAIL)->find($id);
    }

    public function nextNumber(): int
    {
        // shortcut: max + 1 relies on the unique index to reject a rare simultaneous insert; use a sequence table if bookings ever race.
        return max(10000, (int) Reservation::max('number')) + 1;
    }

    public function takenRoomIds(string $checkIn, string $checkOut, ?string $exceptReservationId = null): array
    {
        return ReservationNight::query()
            ->where('night', '>=', $checkIn)
            ->where('night', '<', $checkOut)
            ->when($exceptReservationId, fn ($q) => $q->where('reservation_id', '!=', $exceptReservationId))
            ->distinct()
            ->pluck('room_id')
            ->all();
    }

    public function create(array $data): Reservation
    {
        return Reservation::create($data);
    }

    public function update(Reservation $reservation, array $data): Reservation
    {
        $reservation->update($data);

        return $reservation;
    }

    public function insertNights(Reservation $reservation, string $roomId, array $rates): void
    {
        $rows = [];
        foreach ($rates as $night => $rate) {
            $rows[] = ['reservation_id' => $reservation->id, 'room_id' => $roomId, 'night' => $night, 'rate_ugx' => $rate];
        }
        ReservationNight::insert($rows);
    }

    public function releaseNights(Reservation $reservation, ?string $from = null): void
    {
        ReservationNight::where('reservation_id', $reservation->id)
            ->when($from, fn ($q) => $q->where('night', '>=', $from))
            ->delete();
    }

    public function addCharge(Reservation $reservation, array $data): FolioCharge
    {
        return $reservation->charges()->create($data);
    }

    public function findCharge(Reservation $reservation, string $chargeId): ?FolioCharge
    {
        return $reservation->charges()->whereKey($chargeId)->first();
    }

    public function deleteCharge(FolioCharge $charge): void
    {
        $charge->delete();
    }

    public function inRange(string $from, string $to): Collection
    {
        return $this->withTotals(Reservation::query()->select('reservations.*'))
            ->with('guest:id,full_name')
            ->whereIn('status', self::holding())
            ->where('check_in', '<', $to)
            ->where('check_out', '>', $from)
            ->orderBy('check_in')
            ->get();
    }

    public function arrivalsOn(string $date): Collection
    {
        return $this->withTotals(Reservation::query()->select('reservations.*'))->with(self::ROW)
            ->where('check_in', $date)
            ->whereIn('status', [ReservationStatus::PendingPayment->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])
            ->orderBy('number')
            ->get();
    }

    public function departuresOn(string $date): Collection
    {
        return $this->withTotals(Reservation::query()->select('reservations.*'))->with(self::ROW)
            ->where('check_out', $date)
            ->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
            ->orderBy('number')
            ->get();
    }

    public function expiredHolds(): Collection
    {
        return Reservation::where('status', ReservationStatus::PendingPayment->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->get();
    }

    public function search(string $term, int $limit): Collection
    {
        $number = (int) preg_replace('/\D/', '', $term);

        return Reservation::with(self::ROW)
            ->where(fn (Builder $q) => $q
                ->whereHas('guest', fn ($g) => $g->where('full_name', 'like', "%{$term}%"))
                ->when($number > 0, fn ($w) => $w->orWhere('number', $number)))
            ->latest('check_in')
            ->limit($limit)
            ->get();
    }
}
