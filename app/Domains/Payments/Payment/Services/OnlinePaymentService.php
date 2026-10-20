<?php

namespace App\Domains\Payments\Payment\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Payments\Gateway\DTOs\CheckoutRequest;
use App\Domains\Payments\Gateway\DTOs\GatewayStatus;
use App\Domains\Payments\Gateway\Enums\GatewayState;
use App\Domains\Payments\Gateway\GatewayFee;
use App\Domains\Payments\Gateway\PaymentGateway;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Repositories\PaymentRepositoryInterface;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Exceptions\GatewayException;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Online payments through the gateway (Pesapal), from checkout to settlement.
 *
 * - Network calls never run inside a database transaction.
 * - The gateway's own status is the only truth: an IPN or a return visit only tells us *when* to ask.
 * - Applying a status locks the payment row, so the IPN, the guest's return and the reconcile job can
 *   race safely: the first one settles it, the others see a final status and change nothing.
 */
class OnlinePaymentService
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentRepositoryInterface $repository,
        private PaymentService $payments,
        private AuditLogger $audit,
        private SettingsService $settings,
    ) {}

    /**
     * Ask the gateway for a hosted payment page for a pending payment. Call after the booking's
     * transaction has committed. If the gateway is down the payment is marked failed (nothing was
     * charged) and the guest can try again or choose pay on arrival.
     */
    public function checkout(Payment $payment, string $returnPath = '/payment/return'): Payment
    {
        $payment->load(['reservation.guest', 'facilityBooking.facility']); // full rows: the repository loads them trimmed
        if ($payment->status !== PaymentStatus::Pending || $payment->viaGateway()) {
            return $payment;
        }
        $fee = GatewayFee::fromSettings($this->settings)->quote($payment->amount_ugx);
        try {
            $session = $this->gateway->createCheckout($this->checkoutRequest($payment, $returnPath, $fee['charge_ugx']));
        } catch (GatewayException $e) {
            report($e);

            return $this->repository->update($payment, ['status' => PaymentStatus::Failed->value, 'notes' => Str::limit($e->getMessage(), 250)]);
        }
        if ($session->trackingId === null) {
            return $payment; // simulator: no hosted page, the booking page's test panel settles it
        }

        return $this->repository->update($payment, [
            'gateway' => $this->gateway->name(),
            'gateway_tracking_id' => $session->trackingId,
            'gateway_redirect_url' => $session->redirectUrl,
            'gateway_fee_ugx' => $fee['fee_ugx'],
            'gateway_fee_payer' => $fee['payer'],
        ]);
    }

    /**
     * Staff take an online payment at the desk or POS, or a guest pays a payment link: one pending payment for
     * the amount (all that is due when null), then the hosted page. Earlier requests still waiting are dropped,
     * so a retry never charges twice. Where Pesapal sends the browser afterwards is $returnPath on the website.
     */
    public function request(Reservation|FacilityBooking $owner, PaymentMethod $method, ?int $amountUgx, string $returnPath): Payment
    {
        $this->assertPayable($owner);
        $payment = DB::transaction(fn () => $this->payments->startOnlinePayment($owner, $method, $amountUgx));

        return $this->checkout($payment, $returnPath);
    }

    private function assertPayable(Reservation|FacilityBooking $owner): void
    {
        $closed = $owner instanceof Reservation
            ? in_array($owner->status, [ReservationStatus::Cancelled, ReservationStatus::Expired, ReservationStatus::NoShow], true)
            : $owner->status === FacilityBookingStatus::Cancelled;
        if ($closed) {
            throw new EntityValidationException('This booking is closed, so it can’t take payments.');
        }
    }

    /** IPN, the guest's return from Pesapal, or "Check with Pesapal": ask the gateway and apply the answer. */
    public function syncByTrackingId(string $trackingId): Payment
    {
        $payment = $this->repository->findByTrackingId($trackingId) ?? throw new EntityNotFoundException('Payment', $trackingId);

        return $this->sync($payment);
    }

    public function sync(Payment $payment): Payment
    {
        if ($payment->isRefund()) {
            $original = $payment->original ?? throw new EntityNotFoundException('Payment', (string) $payment->refund_of_id);
            $this->sync($original); // a refund completes when its payment is reported reversed

            return $this->repository->findById($payment->id);
        }
        if (! $payment->viaGateway()) {
            return $payment;
        }
        $status = $this->gateway->fetchStatus($payment->gateway_tracking_id);

        return DB::transaction(function () use ($payment, $status) {
            $locked = $this->repository->findForUpdate($payment->id);
            $settled = $this->apply($locked, $status);
            if ($settled) {
                // Inside the same transaction: the booking is confirmed exactly when the money is recorded.
                $this->payments->afterMoneyIn($this->repository->findById($locked->id));
            }

            return $this->repository->findById($locked->id);
        });
    }

    /**
     * Scheduled every minute: settle online payments whose IPN never came, finish refunds Pesapal has paid,
     * and give up on orders nobody paid for days. Each payment is handled on its own, so one bad answer
     * never blocks the rest.
     *
     * @return array{checked: int, settled: int, abandoned: int, refunds: int}
     */
    public function reconcile(): array
    {
        $result = ['checked' => 0, 'settled' => 0, 'abandoned' => 0, 'refunds' => 0];
        $abandonBefore = now()->subHours(config('payments.abandon_after_hours'));
        foreach ($this->repository->pendingAtGateway(now()->subMinute()) as $payment) {
            try {
                $after = $this->sync($payment);
                $result['checked']++;
                if ($after->status === PaymentStatus::Succeeded) {
                    $result['settled']++;
                } elseif ($after->status === PaymentStatus::Pending && $after->created_at < $abandonBefore) {
                    $this->repository->update($after, ['status' => PaymentStatus::Failed->value, 'notes' => 'No answer from the gateway']);
                    $result['abandoned']++;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        foreach ($this->repository->pendingGatewayRefunds() as $refund) {
            try {
                if ($this->sync($refund)->status === PaymentStatus::Succeeded) {
                    $result['refunds']++;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /** @return bool whether money has just come in */
    private function apply(Payment $payment, GatewayStatus $status): bool
    {
        $details = array_filter([
            'gateway_checked_at' => now(),
            'gateway_confirmation_code' => $status->confirmationCode,
            'gateway_payment_method' => $status->paymentMethod,
            'gateway_account' => $status->account,
        ]);

        return match ($status->state) {
            GatewayState::Completed => $this->complete($payment, $status, $details),
            GatewayState::Failed => $this->fail($payment, $status, $details),
            GatewayState::Reversed => $this->reverse($payment, $details),
            // Not paid yet (Pesapal reports unpaid orders as INVALID): keep waiting.
            GatewayState::Pending, GatewayState::Invalid => $this->wait($payment, $details),
        };
    }

    /** @param array<string, mixed> $details */
    private function wait(Payment $payment, array $details): bool
    {
        $this->repository->update($payment, $details);

        return false;
    }

    /** @param array<string, mixed> $details */
    private function complete(Payment $payment, GatewayStatus $status, array $details): bool
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            $this->repository->update($payment, $details);

            return false; // repeated IPN: already counted
        }
        if ($status->currency && strtoupper($status->currency) !== 'UGX') {
            // Never convert silently. Leave it pending for staff to look at.
            Log::error('Gateway payment in an unexpected currency', ['payment_id' => $payment->id, 'currency' => $status->currency]);
            $this->repository->update($payment, [...$details, 'notes' => "Paid in {$status->currency}: check with Pesapal"]);

            return false;
        }
        // When the guest carries Pesapal's fee it was charged on top: only the rest goes against the bill.
        $guestFee = $payment->guestFee();
        $charged = $payment->amount_ugx + $guestFee;
        $gross = $status->amountUgx ?? $charged;
        $paid = max(0, $gross - $guestFee);
        $note = $gross !== $charged ? 'Gateway amount differs from the request ('.number_format($charged).')' : null;
        // A payment we gave up on (hold expired, method switched) that the guest paid anyway is still money in.
        $wasFailed = $payment->status === PaymentStatus::Failed;
        $this->repository->update($payment, [
            ...$details,
            'status' => PaymentStatus::Succeeded->value,
            'amount_ugx' => $paid,
            'method' => $this->method($status->paymentMethod, $payment->method)->value,
            'received_at' => now(),
            'notes' => $note ?? ($wasFailed ? 'Paid after the request had lapsed' : null),
        ]);
        $this->audit->record('payment.received', $payment->reservation ?? $payment->facilityBooking ?? $payment, [
            'amount_ugx' => ['from' => null, 'to' => $paid],
            'gateway' => ['from' => null, 'to' => $payment->gateway],
            'confirmation_code' => ['from' => null, 'to' => $status->confirmationCode],
        ]);

        return true;
    }

    /** @param array<string, mixed> $details */
    private function fail(Payment $payment, GatewayStatus $status, array $details): bool
    {
        // Never take back money we recorded as received: only a REVERSED status does that.
        $this->repository->update($payment, $payment->status === PaymentStatus::Pending
            ? [...$details, 'status' => PaymentStatus::Failed->value, 'notes' => Str::limit($status->description ?: 'Declined at the gateway', 250)]
            : $details);

        return false;
    }

    /**
     * Pesapal reversed the payment: our refund went through, or the guest's bank or network pulled it back.
     *
     * @param  array<string, mixed>  $details
     */
    private function reverse(Payment $payment, array $details): bool
    {
        $this->repository->update($payment, $details);
        if ($payment->status !== PaymentStatus::Succeeded) {
            return false;
        }
        $pending = $payment->refunds()->where('status', PaymentStatus::Pending->value)->get();
        foreach ($pending as $refund) {
            $this->repository->update($refund, ['status' => PaymentStatus::Succeeded->value, 'received_at' => now()]);
            $this->audit->record('payment.refunded', $payment->reservation ?? $payment, ['amount_ugx' => ['from' => null, 'to' => $refund->amount_ugx]]);
        }
        $returned = (int) $payment->refunds()->where('status', PaymentStatus::Succeeded->value)->sum('amount_ugx');
        if ($pending->isEmpty() && $returned < $payment->amount_ugx) {
            // Nobody here asked for it (chargeback): record the money leaving so the bill shows what is really owed.
            $this->repository->create([
                'reservation_id' => $payment->reservation_id,
                'facility_booking_id' => $payment->facility_booking_id,
                'refund_of_id' => $payment->id,
                'kind' => 'refund',
                'method' => $payment->method->value,
                'amount_ugx' => $payment->amount_ugx - $returned,
                'status' => PaymentStatus::Succeeded->value,
                'notes' => 'Reversed by the payment provider',
                'received_at' => now(),
            ]);
            $this->audit->record('payment.reversed', $payment->reservation ?? $payment, ['amount_ugx' => ['from' => $payment->amount_ugx, 'to' => 0]]);
        }

        return false;
    }

    /** What the guest actually used on Pesapal's page; their earlier choice when it isn't recognised. */
    private function method(?string $gatewayMethod, PaymentMethod $chosen): PaymentMethod
    {
        $m = strtolower((string) $gatewayMethod);

        return match (true) {
            str_contains($m, 'mtn') => PaymentMethod::MtnMomo,
            str_contains($m, 'airtel') => PaymentMethod::AirtelMoney,
            str_contains($m, 'visa'), str_contains($m, 'master'), str_contains($m, 'card'), str_contains($m, 'amex') => PaymentMethod::Card,
            default => $chosen,
        };
    }

    private function checkoutRequest(Payment $payment, string $returnPath, int $chargeUgx): CheckoutRequest
    {
        $reservation = $payment->reservation;
        $booking = $payment->facilityBooking;
        $name = trim($reservation?->guest?->full_name ?? $booking?->guest_name ?? 'Guest');
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
        $return = rtrim(config('app.frontend_url'), '/').$returnPath;

        return new CheckoutRequest(
            merchantReference: $payment->id,
            amountUgx: $chargeUgx,
            description: $reservation
                ? 'Alcawaka Gardens room booking '.$reservation->reference()
                : 'Alcawaka Gardens '.($booking?->facility?->name ?? 'facility').' booking',
            callbackUrl: $return,
            cancellationUrl: $return.'?cancelled=1',
            email: $reservation?->guest?->email ?? $booking?->guest_email,
            phone: $reservation?->guest?->phone ?? $booking?->guest_phone,
            firstName: $first,
            lastName: $last ?: $first,
        );
    }
}
