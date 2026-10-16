<?php

namespace App\Domains\Reports\Report\Services;

use App\Domains\Reports\Report\Repositories\ReportRepositoryInterface;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\Room\Services\RoomService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Occupancy, income, open balances and revenue by source (B02, B11, B13).
 * Hotel days are Africa/Kampala; timestamps are stored in UTC.
 */
class ReportService
{
    private const TZ = 'Africa/Kampala';

    public function __construct(
        private ReportRepositoryInterface $reports,
        private ReservationRepositoryInterface $reservations,
        private RoomService $rooms,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $today = CarbonImmutable::now(self::TZ)->toDateString();
        $rooms = $this->reports->roomCount();
        $occupied = $this->reports->soldRoomNights($today, $today);

        return [
            'date' => $today,
            'occupancy' => ['rooms_sold' => $occupied, 'rooms_total' => $rooms, 'percent' => $rooms ? (int) round($occupied / $rooms * 100) : 0],
            'arrivals' => $this->reservations->arrivalsOn($today),
            'departures' => $this->reservations->departuresOn($today),
            'open_balances' => $this->reports->openBalances(),
            'room_status' => $this->rooms->countByStatus(),
            'revenue_by_source' => $this->bySource($today, $today),
        ];
    }

    /** @return array<string, mixed> */
    public function report(string $period, string $date): array
    {
        $day = CarbonImmutable::parse($date, self::TZ);
        [$from, $to] = match ($period) {
            'daily' => [$day, $day],
            'monthly' => [$day->startOfMonth(), $day->endOfMonth()->startOfDay()],
            'yearly' => [$day->startOfYear(), $day->endOfYear()->startOfDay()],
        };
        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        // Occupancy only counts days that have happened (or today), so "month to date" isn't diluted by the future.
        $occupancyTo = $to->gt($today) && $from->lte($today) ? $today : $to;
        $days = (int) $from->diffInDays($occupancyTo) + 1;
        $rooms = $this->reports->roomCount();
        $sold = $this->reports->soldRoomNights($from->toDateString(), $occupancyTo->toDateString());

        [$fromUtc, $toUtc] = $this->utcRange($from, $to);
        $income = $this->reports->payments($fromUtc, $toUtc)->sum(fn ($p) => $p->kind === 'refund' ? -$p->amount_ugx : $p->amount_ugx);

        return [
            'period' => $period,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'occupancy_percent' => $rooms && $days ? (int) round($sold / ($rooms * $days) * 100) : 0,
            'room_nights_sold' => $sold,
            'income_ugx' => (int) $income,
            'open_balances' => $this->reports->openBalances(),
            'bookings' => $this->reports->bookingsCreated($fromUtc, $toUtc),
            'revenue_by_source' => $this->bySource($from->toDateString(), $to->toDateString()),
            'series' => $this->incomeSeries($period, $day),
        ];
    }

    /**
     * Income per bucket: the last 7 days for a daily report, 12 months otherwise.
     *
     * @return list<array{label: string, key: string, income_ugx: int}>
     */
    private function incomeSeries(string $period, CarbonImmutable $day): array
    {
        if ($period === 'daily') {
            $buckets = collect(CarbonPeriod::create($day->subDays(6), $day))->map(fn ($d) => CarbonImmutable::parse($d));
            $key = fn (CarbonImmutable $d) => $d->toDateString();
            $label = fn (CarbonImmutable $d) => $d->format('D j');
            [$fromUtc, $toUtc] = $this->utcRange($buckets->first(), $buckets->last());
        } else {
            $end = $period === 'yearly' ? $day->endOfYear()->startOfMonth() : $day->startOfMonth();
            $buckets = collect(range(11, 0))->map(fn ($i) => $end->subMonths($i));
            $key = fn (CarbonImmutable $d) => $d->format('Y-m');
            $label = fn (CarbonImmutable $d) => $d->format('M');
            [$fromUtc, $toUtc] = $this->utcRange($buckets->first()->startOfMonth(), $buckets->last()->endOfMonth()->startOfDay());
        }

        $totals = [];
        foreach ($this->reports->payments($fromUtc, $toUtc) as $p) {
            $local = CarbonImmutable::parse($p->received_at, 'UTC')->setTimezone(self::TZ);
            $k = $period === 'daily' ? $local->toDateString() : $local->format('Y-m');
            $totals[$k] = ($totals[$k] ?? 0) + ($p->kind === 'refund' ? -$p->amount_ugx : $p->amount_ugx);
        }

        return $buckets->map(fn ($d) => ['label' => $label($d), 'key' => $key($d), 'income_ugx' => (int) ($totals[$key($d)] ?? 0)])->values()->all();
    }

    /**
     * Room revenue by where the booking came from, plus "Restaurant and services" for every extra.
     *
     * @return list<array{source: string, label: string, amount_ugx: int, percent: int}>
     */
    private function bySource(string $from, string $to): array
    {
        $amounts = array_fill_keys(array_map(fn ($s) => $s->value, ReservationSource::cases()), 0) + ['services' => 0];
        foreach ($this->reports->chargesBySource($from, $to) as $row) {
            $bucket = $row->category === 'room' ? $row->source : 'services';
            $amounts[$bucket] += (int) $row->total;
        }
        [$fromUtc, $toUtc] = $this->utcRange(CarbonImmutable::parse($from, self::TZ), CarbonImmutable::parse($to, self::TZ));
        $amounts['services'] += $this->reports->facilityRevenue($fromUtc, $toUtc);

        $total = array_sum($amounts);
        $labels = collect(ReservationSource::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all() + ['services' => 'Restaurant and services'];

        return collect($amounts)
            ->map(fn ($amount, $source) => [
                'source' => $source,
                'label' => $labels[$source],
                'amount_ugx' => $amount,
                'percent' => $total ? (int) round($amount / $total * 100) : 0,
            ])
            ->values()
            ->all();
    }

    /** @return array{0: string, 1: string} whole hotel days as UTC timestamps */
    private function utcRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            $from->setTimezone(self::TZ)->startOfDay()->utc()->toDateTimeString(),
            $to->setTimezone(self::TZ)->endOfDay()->utc()->toDateTimeString(),
        ];
    }
}
