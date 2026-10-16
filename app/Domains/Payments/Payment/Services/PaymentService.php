<?php

namespace App\Domains\Payments\Payment\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Payments\Payment\DTOs\RefundDTO;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Repositories\PaymentRepositoryInterface;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class PaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private AuditLogger $audit,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    /** @return LazyCollection<int, Payment> */
    public function export(ListQuery $query): LazyCollection
    {
        return $this->repository->cursor($query);
    }

    public function find(string $id): Payment
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Payment', $id);
    }

    /** Money taken at the desk against a guest's bill. Callers wrap this in their transaction. */
    public function recordForReservation(Reservation $reservation, RecordPaymentDTO $dto): Payment
    {
        $reservation->loadMissing(['charges', 'payments']);
        if (in_array($reservation->status, [ReservationStatus::Cancelled, ReservationStatus::Expired, ReservationStatus::NoShow], true)) {
            throw new EntityValidationException("This booking is {$reservation->status->label()}, so it can't take payments.");
        }
        $this->assertDeskMethod($dto->method);
        $balance = $reservation->balance();
        if ($dto->amountUgx > $balance) {
            $message = $balance > 0
                ? 'That is more than the '.self::ugx($balance).' due.'
                : 'Nothing is due on this bill.';
            throw new EntityValidationException($message, ['amount_ugx' => [$message]]);
        }

        $payment = $this->repository->create([
            'reservation_id' => $reservation->id,
            'kind' => 'payment',
            'method' => $dto->method->value,
            'amount_ugx' => $dto->amountUgx,
            'status' => PaymentStatus::Succeeded->value,
            'reference' => $dto->reference,
            'notes' => $dto->notes,
            'recorded_by' => auth()->id(),
            'received_at' => now(),
        ]);
        $this->audit->record('payment.recorded', $reservation, [
            'amount_ugx' => ['from' => null, 'to' => $dto->amountUgx],
            'method' => ['from' => null, 'to' => $dto->method->value],
        ]);
        $reservation->unsetRelation('payments');
        $this->confirmIfPaid($reservation);

        return $payment;
    }

    public function recordForFacilityBooking(FacilityBooking $booking, RecordPaymentDTO $dto): Payment
    {
        $this->assertDeskMethod($dto->method);

        return $this->repository->create([
            'facility_booking_id' => $booking->id,
            'kind' => 'payment',
            'method' => $dto->method->value,
            'amount_ugx' => $dto->amountUgx,
            'status' => PaymentStatus::Succeeded->value,
            'reference' => $dto->reference,
            'recorded_by' => auth()->id(),
            'received_at' => now(),
        ]);
    }

    /** Manager only: money back to the guest, never more than was paid. */
    public function refund(string $paymentId, RefundDTO $dto): Payment
    {
        $payment = $this->find($paymentId);
        if ($payment->isRefund() || $payment->status !== PaymentStatus::Succeeded) {
            throw new EntityValidationException('Only received payments can be refunded.');
        }
        $refundable = $payment->amount_ugx - $this->repository->refundedAmount($payment);
        if ($dto->amountUgx > $refundable) {
            $message = 'You can refund at most '.self::ugx($refundable).' on this payment.';
            throw new EntityValidationException($message, ['amount_ugx' => [$message]]);
        }

        return DB::transaction(function () use ($payment, $dto) {
            $refund = $this->repository->create([
                'reservation_id' => $payment->reservation_id,
                'facility_booking_id' => $payment->facility_booking_id,
                'refund_of_id' => $payment->id,
                'kind' => 'refund',
                'method' => $payment->method->value,
                'amount_ugx' => $dto->amountUgx,
                'status' => PaymentStatus::Succeeded->value,
                'notes' => $dto->reason,
                'recorded_by' => auth()->id(),
                'received_at' => now(),
            ]);
            $this->audit->record('payment.refunded', $payment->reservation ?? $payment, [
                'amount_ugx' => ['from' => null, 'to' => $dto->amountUgx],
                'reason' => ['from' => null, 'to' => $dto->reason],
            ]);

            return $refund;
        });
    }

    /** A pending mobile-money or card payment: staff confirm it arrived, or mark it failed. */
    public function settle(string $paymentId, PaymentStatus $status): Payment
    {
        $payment = $this->find($paymentId);
        if ($payment->status !== PaymentStatus::Pending) {
            throw new EntityValidationException('This payment is already settled.');
        }
        if ($status === PaymentStatus::Pending) {
            throw new EntityValidationException('Choose received or failed.');
        }

        return DB::transaction(function () use ($payment, $status) {
            $updated = $this->repository->update($payment, ['status' => $status->value]);
            $this->audit->record('payment.'.($status === PaymentStatus::Succeeded ? 'confirmed' : 'failed'), $payment->reservation ?? $payment, [
                'amount_ugx' => ['from' => null, 'to' => $payment->amount_ugx],
            ]);
            if ($updated->reservation) {
                $this->confirmIfPaid($updated->reservation);
            }

            return $updated;
        });
    }

    /** A website hold becomes a real booking once it is fully paid, however the money arrived. */
    private function confirmIfPaid(Reservation $reservation): void
    {
        $reservation->load(['charges', 'payments']);
        if ($reservation->status === ReservationStatus::PendingPayment && $reservation->balance() <= 0) {
            $reservation->update(['status' => ReservationStatus::Confirmed->value, 'hold_expires_at' => null]);
            $this->audit->record('reservation.confirmed', $reservation);
        }
    }

    /** @return array<string, mixed> */
    public function summary(?string $date = null): array
    {
        $day = Carbon::parse($date ?? now('Africa/Kampala')->toDateString(), 'Africa/Kampala');

        return $this->repository->summary($day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc());
    }

    private function assertDeskMethod(PaymentMethod $method): void
    {
        if (! in_array($method, PaymentMethod::deskMethods(), true)) {
            throw new EntityValidationException("{$method->label()} payments arrive from the gateway and can't be entered by hand.", [
                'method' => ["{$method->label()} payments arrive from the gateway and can't be entered by hand."],
            ]);
        }
    }

    private static function ugx(int $amount): string
    {
        return 'UGX '.number_format($amount);
    }
}
