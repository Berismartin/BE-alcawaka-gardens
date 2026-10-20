<?php

namespace App\Domains\Payments\Payment\Repositories;

use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

class PaymentRepository implements PaymentRepositoryInterface
{
    use AppliesListQuery;

    private const WITH = [
        'reservation:id,number,guest_id,room_id,status',
        'reservation.guest:id,full_name',
        'reservation.room:id,number',
        'facilityBooking:id,guest_name,facility_id',
        'original:id,gateway,gateway_tracking_id',
        'facilityBooking.facility:id,name',
        'recorder:id,name',
    ];

    /** Search by guest name, booking reference or payment reference. */
    private function base(ListQuery $query): array
    {
        $base = Payment::query()->with(self::WITH);
        if ($query->search) {
            $term = $query->search;
            $number = (int) preg_replace('/\D/', '', $term);
            $base->where(fn (Builder $q) => $q
                ->whereLike('reference', "%{$term}%")
                ->orWhereHas('reservation.guest', fn ($g) => $g->whereLike('full_name', "%{$term}%"))
                ->orWhereHas('facilityBooking', fn ($f) => $f->whereLike('guest_name', "%{$term}%"))
                ->when($number > 0, fn ($w) => $w->orWhereHas('reservation', fn ($r) => $r->where('number', $number))));
            $query = new ListQuery($query->page, $query->perPage, null, $query->sort, $query->filters, $query->from, $query->to);
        }

        return [$base, $query];
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        [$base, $query] = $this->base($query);

        return $this->paginateList($base, $query, dateColumn: 'received_at', defaultSort: '-received_at');
    }

    public function cursor(ListQuery $query): LazyCollection
    {
        [$base, $query] = $this->base(new ListQuery(1, 100, $query->search, null, $query->filters, $query->from, $query->to));
        foreach ($query->filters as $field => $value) {
            $base->where($field, $value);
        }

        return $base
            ->when($query->from, fn ($q) => $q->where('received_at', '>=', now()->parse($query->from, 'Africa/Kampala')->startOfDay()->utc()))
            ->when($query->to, fn ($q) => $q->where('received_at', '<=', now()->parse($query->to, 'Africa/Kampala')->endOfDay()->utc()))
            ->orderBy('received_at')
            ->cursor();
    }

    public function findById(string $id): ?Payment
    {
        return Payment::with(self::WITH)->find($id);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data)->load(self::WITH);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);

        return $payment->refresh()->load(self::WITH);
    }

    public function findForUpdate(string $id): ?Payment
    {
        return Payment::query()->lockForUpdate()->find($id);
    }

    public function findByTrackingId(string $trackingId): ?Payment
    {
        return Payment::where('gateway_tracking_id', $trackingId)->first();
    }

    public function pendingAtGateway(\DateTimeInterface $createdBefore): Collection
    {
        return Payment::where('status', PaymentStatus::Pending->value)->where('kind', 'payment')
            ->whereNotNull('gateway')->whereNotNull('gateway_tracking_id')
            ->where('created_at', '<=', $createdBefore)->oldest()->get();
    }

    public function pendingGatewayRefunds(): Collection
    {
        return Payment::where('kind', 'refund')->where('status', PaymentStatus::Pending->value)
            ->whereHas('original', fn ($q) => $q->whereNotNull('gateway_tracking_id'))->with('original')->get();
    }

    public function hasRefundRequest(Payment $payment): bool
    {
        return $payment->refunds()->where('status', '!=', PaymentStatus::Failed->value)->exists();
    }

    public function refundedAmount(Payment $payment): int
    {
        return (int) $payment->refunds()->where('status', PaymentStatus::Succeeded->value)->sum('amount_ugx');
    }

    public function pendingFor(Reservation|FacilityBooking $owner): Collection
    {
        return Payment::where($owner instanceof Reservation ? 'reservation_id' : 'facility_booking_id', $owner->id)
            ->where('kind', 'payment')->where('status', PaymentStatus::Pending->value)->get();
    }

    public function summary(string $from, string $to): array
    {
        $range = Payment::query()->where('kind', 'payment')->whereBetween('received_at', [$from, $to]);
        $bucket = fn (PaymentStatus $s) => (clone $range)->where('status', $s->value)
            ->selectRaw('count(*) as n, coalesce(sum(amount_ugx), 0) as total')->first();

        $received = $bucket(PaymentStatus::Succeeded);
        $waiting = $bucket(PaymentStatus::Pending);
        $failed = $bucket(PaymentStatus::Failed);

        return [
            'received' => ['count' => (int) $received->n, 'total' => (int) $received->total],
            'waiting' => ['count' => (int) $waiting->n, 'total' => (int) $waiting->total],
            'failed' => ['count' => (int) $failed->n, 'total' => (int) $failed->total],
            'unmatched' => Payment::whereNull('reservation_id')->whereNull('facility_booking_id')->count(),
        ];
    }
}
