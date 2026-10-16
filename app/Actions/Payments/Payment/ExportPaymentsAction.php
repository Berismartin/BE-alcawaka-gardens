<?php

namespace App\Actions\Payments\Payment;

use App\Domains\Payments\Payment\Services\PaymentService;
use App\Support\Query\ListQuery;
use Illuminate\Support\LazyCollection;

class ExportPaymentsAction
{
    public function __construct(private PaymentService $service) {}

    /** @return LazyCollection<int, list<string|int>> CSV rows, header first */
    public function execute(ListQuery $query): LazyCollection
    {
        $header = ['Received (Kampala)', 'Kind', 'Guest', 'Booking', 'Method', 'Reference', 'Amount (UGX)', 'Status', 'Recorded by'];

        return LazyCollection::make(fn () => yield $header)->concat($this->service->export($query)->map(fn ($p) => [
            $p->received_at->timezone('Africa/Kampala')->format('Y-m-d H:i'),
            $p->kind,
            $p->reservation?->guest?->full_name ?? $p->facilityBooking?->guest_name ?? '',
            $p->reservation?->reference() ?? $p->facilityBooking?->facility?->name ?? '',
            $p->method->label(),
            $p->reference ?? '',
            $p->signedAmount(),
            $p->status->value,
            $p->recorder?->name ?? '',
        ]));
    }
}
