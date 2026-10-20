<?php

namespace App\Domains\Payments\PaymentLink\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Services\OnlinePaymentService;
use App\Domains\Payments\PaymentLink\Repositories\PaymentLinkRepositoryInterface;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Mail\PaymentLinkMail;
use App\Models\Facilities\FacilityBooking;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentLink;
use App\Models\Reservations\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Invoices with a "pay now" link: staff email one; the guest opens /pay/{token} and pays through the gateway.
 * The link never stores a gateway order: each visit opens a fresh checkout for what is still due, so it can't
 * go stale, charge twice, or charge more than is owed.
 */
class PaymentLinkService
{
    public const DAYS_VALID = 14;

    public function __construct(
        private PaymentLinkRepositoryInterface $repository,
        private OnlinePaymentService $online,
        private SettingsService $settings,
        private AuditLogger $audit,
    ) {}

    /** @return array{link: PaymentLink, emailed: bool} */
    public function send(Reservation|FacilityBooking $owner, string $email, ?int $amountUgx, ?string $message): array
    {
        $due = self::due($owner);
        if ($due < 1 || self::closed($owner)) {
            throw new EntityValidationException('Nothing is due on this booking.');
        }
        if ($amountUgx !== null && ($amountUgx < 1 || $amountUgx > $due)) {
            $text = 'Enter an amount up to the UGX '.number_format($due).' due.';
            throw new EntityValidationException($text, ['amount_ugx' => [$text]]);
        }

        $link = DB::transaction(function () use ($owner, $email, $amountUgx, $message) {
            $link = $this->repository->create([
                'token' => Str::random(48),
                $owner instanceof Reservation ? 'reservation_id' : 'facility_booking_id' => $owner->id,
                'amount_ugx' => $amountUgx,
                'email' => $email,
                'message' => $message,
                'sent_by' => auth()->id(),
                'expires_at' => now()->addDays(self::DAYS_VALID),
            ]);
            $this->audit->record('payment_link.sent', $owner, [
                'email' => ['from' => null, 'to' => $email],
                'amount_ugx' => ['from' => null, 'to' => $amountUgx ?? self::due($owner)],
            ]);

            return $link;
        });

        // After the commit; a mail outage keeps the link, which staff can still copy or WhatsApp.
        try {
            Mail::to($email)->send(new PaymentLinkMail($link, $this->settings->all()));
            $emailed = true;
        } catch (Throwable $e) {
            report($e);
            $emailed = false;
        }

        return ['link' => $link, 'emailed' => $emailed];
    }

    /** The guest's view. Coming back from the gateway, the latest attempt is settled first so the page shows the outcome. */
    public function show(string $token): PaymentLink
    {
        $link = $this->find($token);
        if ($link->lastPayment?->status === PaymentStatus::Pending && $link->lastPayment->viaGateway()) {
            try {
                $this->online->sync($link->lastPayment);
            } catch (Throwable $e) {
                report($e); // the page still shows; the IPN and reconcile job settle it shortly
            }
            $link = $this->find($token);
        }

        return $link;
    }

    /** The guest pressed Pay: a fresh checkout for what is due now (or the link's amount if smaller). */
    public function checkout(string $token, PaymentMethod $method): Payment
    {
        $link = $this->find($token);
        $owner = $link->owner();
        $status = self::status($link);
        if ($status !== 'open') {
            throw new EntityValidationException(match ($status) {
                'paid' => 'This invoice is already paid. Thank you!',
                'expired' => 'This payment link has expired. Please ask us for a new one.',
                default => 'This booking is closed, so it can’t take payments. Please contact us.',
            });
        }
        $payment = $this->online->request($owner, $method, self::payNow($link), "/pay/{$token}");
        $this->repository->update($link, ['last_payment_id' => $payment->id]);

        return $payment;
    }

    public static function due(Reservation|FacilityBooking $owner): int
    {
        $owner->loadMissing($owner instanceof Reservation ? ['charges', 'payments'] : ['payments']);

        return max(0, $owner instanceof Reservation ? $owner->balance() : $owner->amount_ugx - $owner->paidAmount());
    }

    public static function payNow(PaymentLink $link): int
    {
        $due = self::due($link->owner());

        return $link->amount_ugx ? min($link->amount_ugx, $due) : $due;
    }

    /** open | paid | expired | closed */
    public static function status(PaymentLink $link): string
    {
        $owner = $link->owner();

        return match (true) {
            self::closed($owner) => 'closed',
            self::due($owner) < 1 => 'paid',
            $link->expires_at->isPast() => 'expired',
            default => 'open',
        };
    }

    private static function closed(Reservation|FacilityBooking $owner): bool
    {
        return $owner instanceof Reservation
            ? in_array($owner->status, [ReservationStatus::Cancelled, ReservationStatus::Expired, ReservationStatus::NoShow], true)
            : $owner->status === FacilityBookingStatus::Cancelled;
    }

    private function find(string $token): PaymentLink
    {
        return $this->repository->findByToken($token) ?? throw new EntityNotFoundException('Payment link', 'unknown');
    }
}
