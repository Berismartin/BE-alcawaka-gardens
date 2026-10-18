<?php

namespace App\Domains\Reservations\Reservation\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Domains\Reservations\Reservation\DTOs\ChargeDTO;
use App\Domains\Reservations\Reservation\Enums\ChargeCategory;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Mail\ReceiptMail;
use App\Models\Reservations\Reservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** The guest's bill: extras added during the stay, payments against it, the receipt at the end. */
class FolioService
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private ReservationService $reservations,
        private PaymentService $payments,
        private SettingsService $settings,
        private AuditLogger $audit,
    ) {}

    public function addCharge(string $reservationId, ChargeDTO $dto): Reservation
    {
        $reservation = $this->reservations->find($reservationId);
        $this->assertOpen($reservation);
        if ($dto->category === ChargeCategory::Room) {
            throw new EntityValidationException('Room nights are charged automatically. Pick another category.', ['category' => ['Room nights are charged automatically.']]);
        }

        DB::transaction(function () use ($reservation, $dto) {
            $this->repository->addCharge($reservation, [
                'category' => $dto->category->value,
                'description' => $dto->description,
                'amount_ugx' => $dto->amountUgx,
                'posted_on' => ReservationService::today(),
                'created_by' => auth()->id(),
            ]);
            $this->audit->record('folio.charge_added', $reservation, [
                'description' => ['from' => null, 'to' => $dto->description],
                'amount_ugx' => ['from' => null, 'to' => $dto->amountUgx],
            ]);
        });

        return $this->reservations->find($reservationId);
    }

    public function removeCharge(string $reservationId, string $chargeId): Reservation
    {
        $reservation = $this->reservations->find($reservationId);
        $this->assertOpen($reservation);
        $charge = $this->repository->findCharge($reservation, $chargeId) ?? throw new EntityNotFoundException('Charge', $chargeId);
        if ($charge->category === ChargeCategory::Room) {
            throw new EntityValidationException('The room charge follows the dates. Change the booking instead.');
        }

        DB::transaction(function () use ($reservation, $charge) {
            $this->repository->deleteCharge($charge);
            $this->audit->record('folio.charge_removed', $reservation, [
                'description' => ['from' => $charge->description, 'to' => null],
                'amount_ugx' => ['from' => $charge->amount_ugx, 'to' => null],
            ]);
        });

        return $this->reservations->find($reservationId);
    }

    public function recordPayment(string $reservationId, RecordPaymentDTO $dto): Reservation
    {
        $reservation = $this->reservations->find($reservationId);
        DB::transaction(fn () => $this->payments->recordForReservation($reservation, $dto));

        return $this->reservations->find($reservationId);
    }

    public function sendReceipt(string $reservationId, ?string $email = null): string
    {
        $reservation = $this->reservations->find($reservationId);
        $to = $email ?: $reservation->guest->email;
        if (! $to) {
            throw new EntityValidationException('This guest has no email address. Add one, or print the receipt.', ['email' => ['Enter an email address.']]);
        }
        Mail::to($to)->send($this->receipt($reservationId));
        $this->audit->record('folio.receipt_sent', $reservation, ['email' => ['from' => null, 'to' => $to]]);

        return $to;
    }

    /** The bill as a mailable; ->pdf() gives the printable file. */
    public function receipt(string $reservationId): ReceiptMail
    {
        return new ReceiptMail($this->reservations->find($reservationId), $this->settings->all());
    }

    private function assertOpen(Reservation $reservation): void
    {
        if (! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::CheckedIn, ReservationStatus::PendingPayment], true)) {
            throw new EntityValidationException("This bill is closed because the booking is {$reservation->status->label()}.");
        }
    }
}
