<?php

namespace App\Domains\Payments\Payment\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Payments\Payment\DTOs\RefundDTO;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Repositories\PaymentRepositoryInterface;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Mail\BookingConfirmedMail;
use App\Mail\FacilityBookingConfirmedMail;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    public function __construct(
        private PaymentRepositoryInterface $repository,
        private AuditLogger $audit,
        private SettingsService $settings,
        private NotificationService $notifications,
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

    /**
     * A website guest pays online (a room booking or a facility booking): one pending payment for
     * what is due. Any earlier request still waiting is dropped, so "resend" or "use another method"
     * never double-charges.
     */
    public function startOnlinePayment(Reservation|FacilityBooking $owner, PaymentMethod $method): Payment
    {
        if (! in_array($method, [PaymentMethod::MtnMomo, PaymentMethod::AirtelMoney, PaymentMethod::Card], true)) {
            throw new EntityValidationException('Choose mobile money or card.', ['payment_method' => ['Choose mobile money or card.']]);
        }
        $isRoom = $owner instanceof Reservation;
        $owner->load($isRoom ? ['charges', 'payments'] : ['payments']);
        $this->failPending($owner);

        $payment = $this->repository->create([
            $isRoom ? 'reservation_id' : 'facility_booking_id' => $owner->id,
            'kind' => 'payment',
            'method' => $method->value,
            'amount_ugx' => $isRoom ? $owner->balance() : $owner->amount_ugx - $owner->paidAmount(),
            'status' => PaymentStatus::Pending->value,
            'reference' => 'WEB-'.strtoupper(Str::random(8)),
            'received_at' => now(),
        ]);
        $this->audit->record('payment.requested', $owner, [
            'amount_ugx' => ['from' => null, 'to' => $payment->amount_ugx],
            'method' => ['from' => null, 'to' => $method->value],
        ]);

        return $payment;
    }

    /** Requests nobody approved: marked failed so they never count as money in. */
    public function failPending(Reservation|FacilityBooking $owner): void
    {
        foreach ($this->repository->pendingFor($owner) as $payment) {
            $this->repository->update($payment, ['status' => PaymentStatus::Failed->value, 'notes' => 'Not approved']);
        }
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
            $message = $refundable > 0 ? 'You can refund at most '.self::ugx($refundable).' on this payment.' : 'This payment has already been refunded in full.';
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
            if ($updated->facilityBooking) {
                $this->confirmFacilityIfPaid($updated->facilityBooking);
            }

            return $updated;
        });
    }

    /** A website hold becomes a real booking once it is fully paid, however the money arrived. */
    private function confirmIfPaid(Reservation $reservation): void
    {
        // refresh(): payments load their reservation with only a few columns.
        $reservation->refresh()->load(['charges', 'payments', 'guest', 'roomType']);
        if ($reservation->status === ReservationStatus::PendingPayment && $reservation->balance() <= 0) {
            $reservation->update(['status' => ReservationStatus::Confirmed->value, 'hold_expires_at' => null]);
            $this->audit->record('reservation.confirmed', $reservation);
            $this->sendBookingMail($reservation, 'confirmed');
        }
    }

    /** A website facility booking is confirmed once paid in full; the guest gets the confirmation. */
    private function confirmFacilityIfPaid(FacilityBooking $booking): void
    {
        $booking->refresh()->load(['payments', 'facility']);
        if ($booking->status !== FacilityBookingStatus::PendingPayment || $booking->charge_mode !== ChargeMode::Online || $booking->paidAmount() < $booking->amount_ugx) {
            return;
        }
        $booking->update(['status' => FacilityBookingStatus::Confirmed->value, 'hold_expires_at' => null]);
        $this->audit->record('facility_booking.paid', $booking, ['amount_ugx' => ['from' => null, 'to' => $booking->amount_ugx]]);
        $this->announceFacilityBooking($booking);
    }

    /** A website facility booking is in: staff are told, the guest gets the confirmation. A mail outage never undoes it. */
    public function announceFacilityBooking(FacilityBooking $booking): void
    {
        $booking->loadMissing(['payments', 'facility']);
        $this->notifications->notify('facilities.view', new PortalNotification(
            "{$booking->facility->name} booked online",
            "{$booking->guest_name} · {$booking->party_size} ".($booking->party_size === 1 ? 'person' : 'people').' · '.$booking->starts_at->timezone('Africa/Kampala')->format('D j M H:i'),
            '/portal/facilities',
            'i-lucide-waves',
        ));
        if ($booking->guest_email) {
            try {
                Mail::to($booking->guest_email)->send(new FacilityBookingConfirmedMail($booking, $this->settings->all()));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Confirmed, changed or cancelled: tells the guest by email. The change is already saved,
     * so a mail outage is reported, never allowed to undo it.
     */
    public function sendBookingMail(Reservation $reservation, string $kind): void
    {
        $email = $reservation->guest?->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new BookingConfirmedMail($reservation, $this->settings->all(), $kind));
            $this->audit->record('reservation.confirmation_sent', $reservation, ['email' => ['from' => null, 'to' => $email]]);
        } catch (Throwable $e) {
            report($e);
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
