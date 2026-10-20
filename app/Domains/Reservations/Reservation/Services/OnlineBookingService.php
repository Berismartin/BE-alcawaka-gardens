<?php

namespace App\Domains\Reservations\Reservation\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Content\Enquiry\DTOs\CreateEnquiryDTO;
use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use App\Domains\Content\Enquiry\Services\EnquiryService;
use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;
use App\Domains\Payments\Payment\Enums\PaymentMethod;
use App\Domains\Payments\Payment\Enums\PaymentStatus;
use App\Domains\Payments\Payment\Services\OnlinePaymentService;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Domains\Reservations\Reservation\DTOs\CreateReservationDTO;
use App\Domains\Reservations\Reservation\DTOs\OnlineBookingDTO;
use App\Domains\Reservations\Reservation\DTOs\StayDTO;
use App\Domains\Reservations\Reservation\DTOs\UpdateReservationDTO;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\RoomType\Repositories\RoomTypeRepositoryInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Mail\ReceiptMail;
use App\Models\Content\Enquiry;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking on the public website (A02–A07). Guests choose a room type; we assign a
 * free room, hold it while they pay, and give them a secret key to manage it.
 */
class OnlineBookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private ReservationService $reservations,
        private ReservationRepositoryInterface $repository,
        private RoomTypeRepositoryInterface $roomTypes,
        private PaymentService $payments,
        private OnlinePaymentService $online,
        private EnquiryService $enquiries,
        private FolioService $folio,
        private AuditLogger $audit,
    ) {}

    /**
     * Every bookable room type for the stay: how many are left and the price, sold-out types included.
     *
     * @return list<array{type: RoomType, rooms_left: int, nightly: array<string, int>, total_ugx: ?int, fits: bool}>
     */
    public function search(StayDTO $stay): array
    {
        $this->assertFuture($stay);
        $free = collect($this->availability->search(new StayDTO($stay->checkIn, $stay->checkOut)))->groupBy(fn ($r) => $r['room']->room_type_id);

        return $this->roomTypes->all(activeOnly: true)->map(function (RoomType $type) use ($free, $stay) {
            $rooms = $free->get($type->id, collect());

            return [
                'type' => $type,
                'rooms_left' => $rooms->count(),
                'nightly' => $rooms->first()['nightly'] ?? [],
                'total_ugx' => $rooms->first()['total_ugx'] ?? null,
                'fits' => $stay->guests() <= $type->max_guests,
            ];
        })->values()->all();
    }

    /** @return array{reservation: Reservation, key: string} */
    public function book(OnlineBookingDTO $dto): array
    {
        $this->assertFuture($dto->stay);
        if (! $dto->guest->email) {
            throw new EntityValidationException('Enter your email so we can send the confirmation.', ['guest.email' => ['Enter your email.']]);
        }
        $candidates = collect($this->availability->search(new StayDTO($dto->stay->checkIn, $dto->stay->checkOut, $dto->stay->adults, $dto->stay->children, $dto->roomTypeId)))
            ->sortBy(fn ($r) => $r['room']->status->value === 'available' ? 0 : 1); // a ready room first
        if ($candidates->isEmpty()) {
            throw new ConflictException('Sorry, that room type has just sold out for those dates.', ['room_type_id' => ['Sold out for those dates.']]);
        }

        $requests = trim(implode('. ', array_filter([$dto->arrivalTime ? "Arriving around {$dto->arrivalTime}" : null, $dto->specialRequests])));
        $key = Str::random(40);
        $payOnArrival = $dto->paymentMethod === PaymentMethod::Cash;

        $payment = null;
        $result = DB::transaction(function () use ($dto, $candidates, $requests, $key, $payOnArrival, &$payment) {
            foreach ($candidates as $candidate) {
                try {
                    $reservation = $this->reservations->create(new CreateReservationDTO(
                        stay: $dto->stay,
                        roomId: $candidate['room']->id,
                        source: ReservationSource::Website,
                        guest: $dto->guest,
                        specialRequests: $requests ?: null,
                        holdMinutes: $payOnArrival ? null : config('payments.hold_minutes'),
                    ));
                } catch (ConflictException) {
                    continue; // someone took that room a moment ago: try the next one
                }
                $this->repository->update($reservation, ['guest_key' => $key]);
                if (! $payOnArrival) {
                    $payment = $this->payments->startOnlinePayment($reservation, $dto->paymentMethod);
                }

                return ['reservation' => $this->reservations->find($reservation->id), 'key' => $key];
            }
            throw new ConflictException('Sorry, that room type has just sold out for those dates.', ['room_type_id' => ['Sold out for those dates.']]);
        });
        if ($payOnArrival) {
            $this->payments->sendBookingMail($result['reservation'], 'confirmed');
        }
        if ($payment) {
            // The room is held and committed; now open the hosted payment page (never inside the transaction).
            $this->online->checkout($payment);
            $result['reservation'] = $this->reservations->find($result['reservation']->id);
        }

        return $result;
    }

    /** The guest's view of their booking. An unpaid hold that ran out is expired on the spot. */
    public function show(string $reference, string $key): Reservation
    {
        $reservation = $this->forGuest($reference, $key);
        if ($reservation->status === ReservationStatus::PendingPayment && $reservation->hold_expires_at?->isPast()) {
            $this->reservations->expireHolds();
            $reservation = $this->forGuest($reference, $key);
        }

        return $reservation;
    }

    /** The guest's receipt (or invoice while money is due), once the booking is confirmed. */
    public function receipt(string $reference, string $key): ReceiptMail
    {
        $reservation = $this->show($reference, $key);
        if (! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::CheckedIn, ReservationStatus::CheckedOut], true)) {
            throw new EntityValidationException('A receipt is ready once the booking is confirmed.');
        }

        return $this->folio->receipt($reservation->id);
    }

    /** "Resend request" or "Use a different method" while the hold lasts. */
    public function pay(string $reference, string $key, PaymentMethod $method): Reservation
    {
        $reservation = $this->show($reference, $key);
        if ($reservation->status !== ReservationStatus::PendingPayment) {
            throw new EntityValidationException($reservation->status === ReservationStatus::Expired
                ? 'The hold on this room has ended. Please search again.'
                : 'This booking is already paid.');
        }
        if ($method === PaymentMethod::Cash) {
            // Gave up on paying online: keep the room and pay at the front desk on arrival.
            DB::transaction(function () use ($reservation) {
                $this->payments->failPending($reservation);
                $this->repository->update($reservation, ['status' => ReservationStatus::Confirmed->value, 'hold_expires_at' => null]);
                $this->audit->record('reservation.confirmed', $reservation, ['payment' => ['from' => null, 'to' => 'cash on arrival']]);
            });
            $this->payments->sendBookingMail($this->reservations->find($reservation->id), 'confirmed');
        } else {
            $this->online->checkout(DB::transaction(fn () => $this->payments->startOnlinePayment($reservation, $method)));
        }

        return $this->forGuest($reference, $key);
    }

    /** Test mode only: stands in for the gateway telling us the guest approved or declined. */
    public function simulate(string $reference, string $key, bool $approve): Reservation
    {
        if (! config('payments.simulator')) {
            throw new EntityNotFoundException('Simulator', 'off');
        }
        $reservation = $this->show($reference, $key);
        $pending = $reservation->payments->firstWhere('status', PaymentStatus::Pending)
            ?? throw new EntityValidationException('There is no payment waiting for approval.');
        $this->payments->settle($pending->id, $approve ? PaymentStatus::Succeeded : PaymentStatus::Failed);

        return $this->forGuest($reference, $key);
    }

    /** "My bookings": reference plus the email or phone used to book. Returns the key to manage it. */
    public function lookup(string $reference, string $contact): Reservation
    {
        $reservation = $this->findByReference($reference);
        $guest = $reservation?->guest;
        $matches = $guest && (
            ($guest->email && strcasecmp($guest->email, trim($contact)) === 0)
            || ($guest->phone && $guest->phone === PhoneNumber::normalize($contact))
        );
        if (! $matches) {
            throw new EntityValidationException('We couldn’t find a booking with those details. Check the reference and the email or phone you booked with.', ['reference' => ['Not found.']]);
        }
        if (! $reservation->guest_key) {
            $this->repository->update($reservation, ['guest_key' => Str::random(40)]);
        }

        return $reservation->refresh();
    }

    /** Date change or cancellation request: lands in Enquiries for the front desk to confirm. */
    public function request(string $reference, string $key, string $type, ?string $newCheckIn, ?string $newCheckOut, ?string $note): void
    {
        $reservation = $this->show($reference, $key);
        if (! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::PendingPayment], true)) {
            throw new EntityValidationException("This booking is {$reservation->status->label()}, so it can't be changed online. Please call us.");
        }
        $guest = $reservation->guest;
        $what = $type === 'cancel'
            ? 'Please cancel this booking.'
            : 'Please move this booking to '.CarbonImmutable::parse($newCheckIn)->format('D j M').' – '.CarbonImmutable::parse($newCheckOut)->format('D j M Y').'.';

        DB::transaction(function () use ($reservation, $guest, $type, $what, $note, $newCheckIn, $newCheckOut) {
            $this->enquiries->create(new CreateEnquiryDTO(
                name: $guest->full_name,
                phone: $guest->phone ?? '—',
                message: trim("{$reservation->reference()} · {$reservation->roomType->name} · {$reservation->check_in->format('D j M')} – {$reservation->check_out->format('D j M Y')}.\n{$what}\n{$note}"),
                email: $guest->email,
                eventType: $type === 'cancel' ? 'Cancellation request' : 'Date change request',
                eventDate: $type === 'cancel' ? null : $newCheckIn,
                reservationId: $reservation->id,
                requestType: $type,
                requestedCheckIn: $type === 'cancel' ? null : $newCheckIn,
                requestedCheckOut: $type === 'cancel' ? null : $newCheckOut,
            ));
            $this->audit->record('reservation.guest_request', $reservation, ['request' => ['from' => null, 'to' => $type === 'cancel' ? 'cancellation' : 'date change']]);
        });
    }

    /**
     * Staff apply a guest's request in one click: the booking moves (to another room of the
     * same type if its own is taken) or is cancelled, the request is closed and the guest emailed.
     */
    public function applyRequest(string $enquiryId): Enquiry
    {
        $enquiry = $this->enquiries->find($enquiryId);
        if (! $enquiry->request_type || ! $enquiry->reservation_id) {
            throw new EntityValidationException('This enquiry is not a booking request.');
        }
        if ($enquiry->status === EnquiryStatus::Closed) {
            throw new EntityValidationException('This request is already closed.');
        }
        $reservation = $this->reservations->find($enquiry->reservation_id);
        $change = $enquiry->request_type === 'change';

        $updated = DB::transaction(function () use ($enquiry, $reservation, $change) {
            if ($change) {
                $this->moveDates($reservation, $enquiry->requested_check_in, $enquiry->requested_check_out);
            } else {
                $this->reservations->cancel($reservation->id, 'Guest asked to cancel online');
            }
            $this->audit->record('reservation.guest_request_applied', $reservation, ['request' => ['from' => null, 'to' => $change ? 'date change' : 'cancellation']]);
            $done = $change ? 'Dates changed' : 'Booking cancelled';

            return $this->enquiries->update($enquiry->id, [
                'status' => EnquiryStatus::Closed->value,
                'notes' => trim(($enquiry->notes ? $enquiry->notes."\n" : '')."{$done} by ".auth()->user()?->name.'.'),
            ]);
        });
        $this->payments->sendBookingMail($this->reservations->find($reservation->id), $change ? 'changed' : 'cancelled');

        return $updated;
    }

    /** Same room if it is free for the new dates, else any free room of the same type. */
    private function moveDates(Reservation $reservation, string $checkIn, string $checkOut): void
    {
        try {
            $this->reservations->update($reservation->id, new UpdateReservationDTO(['check_in' => $checkIn, 'check_out' => $checkOut]));

            return;
        } catch (ConflictException) {
            // its room is taken for those dates: look for another of the same type
        }
        $free = $this->availability->search(new StayDTO($checkIn, $checkOut, $reservation->adults, $reservation->children, $reservation->room_type_id), $reservation->id);
        foreach ($free as $candidate) {
            try {
                $this->reservations->update($reservation->id, new UpdateReservationDTO(['room_id' => $candidate['room']->id, 'check_in' => $checkIn, 'check_out' => $checkOut]));

                return;
            } catch (ConflictException) {
                continue;
            }
        }
        $message = "No {$reservation->roomType->name} is free for those dates. Call the guest to offer other dates or another room type.";
        throw new ConflictException($message, ['requested_check_in' => [$message]]);
    }

    private function forGuest(string $reference, string $key): Reservation
    {
        $reservation = $this->findByReference($reference);
        if (! $reservation || ! $reservation->guest_key || ! hash_equals($reservation->guest_key, $key)) {
            throw new EntityNotFoundException('Booking', $reference);
        }

        return $reservation;
    }

    private function findByReference(string $reference): ?Reservation
    {
        $number = (int) preg_replace('/\D/', '', $reference);

        return $number ? $this->repository->findByNumber($number) : null;
    }

    private function assertFuture(StayDTO $stay): void
    {
        if ($stay->checkIn < ReservationService::today()) {
            throw new EntityValidationException('Choose a check-in date from today.', ['check_in' => ['Choose a check-in date from today.']]);
        }
        $this->availability->assertStay($stay);
    }
}
