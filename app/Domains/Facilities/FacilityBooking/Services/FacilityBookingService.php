<?php

namespace App\Domains\Facilities\FacilityBooking\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Facilities\Facility\Services\FacilityService;
use App\Domains\Facilities\FacilityBooking\DTOs\CreateFacilityBookingDTO;
use App\Domains\Facilities\FacilityBooking\Enums\ChargeMode;
use App\Domains\Facilities\FacilityBooking\Enums\FacilityBookingStatus;
use App\Domains\Facilities\FacilityBooking\Repositories\FacilityBookingRepositoryInterface;
use App\Domains\Payments\Payment\DTOs\RecordPaymentDTO;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Domains\Reservations\Reservation\Enums\ChargeCategory;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Facilities\FacilityBooking;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pool passes, garden events, meeting-room hire. A booking is either included
 * for in-house guests, added to their room bill, paid now, or left to pay later.
 */
class FacilityBookingService
{
    public function __construct(
        private FacilityBookingRepositoryInterface $repository,
        private FacilityService $facilities,
        private ReservationService $reservations,
        private ReservationRepositoryInterface $reservationRepository,
        private PaymentService $payments,
        private AuditLogger $audit,
    ) {}

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): FacilityBooking
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Facility booking', $id);
    }

    public function create(CreateFacilityBookingDTO $dto): FacilityBooking
    {
        $facility = $this->facilities->find($dto->facilityId);
        if (! $facility->is_active) {
            throw new EntityValidationException("{$facility->name} is closed.", ['facility_id' => ["{$facility->name} is closed."]]);
        }
        if ($facility->capacity && $dto->partySize > $facility->capacity) {
            $message = "{$facility->name} holds at most {$facility->capacity} people.";
            throw new EntityValidationException($message, ['party_size' => [$message]]);
        }
        $startsAt = Carbon::parse($dto->startsAt, 'Africa/Kampala')->utc();
        if ($startsAt->lt(now()->subHour())) {
            throw new EntityValidationException("The time can't be in the past.", ['starts_at' => ["The time can't be in the past."]]);
        }

        $reservation = null;
        if (in_array($dto->chargeMode, [ChargeMode::Included, ChargeMode::Folio], true)) {
            if (! $dto->reservationId) {
                throw new EntityValidationException('Pick the in-house guest this is for.', ['reservation_id' => ['Pick the in-house guest this is for.']]);
            }
            $reservation = $this->reservations->find($dto->reservationId);
            if ($reservation->status !== ReservationStatus::CheckedIn) {
                throw new EntityValidationException("{$reservation->guest->full_name} isn't checked in, so nothing can go on the room bill.", ['reservation_id' => ['That guest is not checked in.']]);
            }
        } elseif (! $dto->guestName) {
            throw new EntityValidationException('Enter the guest’s name.', ['guest_name' => ['Enter the guest’s name.']]);
        }

        $amount = $dto->chargeMode === ChargeMode::Included ? 0 : ($dto->amountUgx ?? $facility->price_ugx ?? 0);
        if ($dto->chargeMode !== ChargeMode::Included && $amount < 1) {
            throw new EntityValidationException('Enter the amount to charge.', ['amount_ugx' => ['Enter the amount to charge.']]);
        }
        if ($dto->chargeMode === ChargeMode::Paid && ! $dto->paymentMethod) {
            throw new EntityValidationException('Choose how the guest paid.', ['payment_method' => ['Choose how the guest paid.']]);
        }

        $id = DB::transaction(function () use ($dto, $facility, $reservation, $startsAt, $amount) {
            $booking = $this->repository->create([
                'facility_id' => $facility->id,
                'reservation_id' => $reservation?->id,
                'guest_name' => $reservation ? $reservation->guest->full_name.' · Room '.$reservation->room->number : $dto->guestName,
                'guest_phone' => $reservation ? $reservation->guest->phone : $dto->guestPhone,
                'starts_at' => $startsAt,
                'party_size' => $dto->partySize,
                'charge_mode' => $dto->chargeMode->value,
                'amount_ugx' => $amount,
                'status' => $dto->chargeMode === ChargeMode::PayLater ? FacilityBookingStatus::PendingPayment->value : FacilityBookingStatus::Confirmed->value,
                'notes' => $dto->notes,
                'created_by' => auth()->id(),
            ]);

            if ($dto->chargeMode === ChargeMode::Folio) {
                $this->reservationRepository->addCharge($reservation, [
                    'category' => ChargeCategory::Facility->value,
                    'description' => $facility->name.' · '.$startsAt->copy()->timezone('Africa/Kampala')->format('D j M H:i'),
                    'amount_ugx' => $amount,
                    'posted_on' => ReservationService::today(),
                    'created_by' => auth()->id(),
                ]);
                $this->audit->record('folio.charge_added', $reservation, [
                    'description' => ['from' => null, 'to' => $facility->name],
                    'amount_ugx' => ['from' => null, 'to' => $amount],
                ]);
            }
            if ($dto->chargeMode === ChargeMode::Paid) {
                $this->payments->recordForFacilityBooking($booking, new RecordPaymentDTO($dto->paymentMethod, $amount));
            }
            $this->audit->record('facility_booking.created', $booking, ['facility' => ['from' => null, 'to' => $facility->name]]);

            return $booking->id;
        });

        return $this->find($id);
    }

    /** Pay-later booking: the guest pays at the desk. */
    public function recordPayment(string $id, RecordPaymentDTO $dto): FacilityBooking
    {
        $booking = $this->find($id);
        if ($booking->status !== FacilityBookingStatus::PendingPayment) {
            throw new EntityValidationException('This booking has nothing left to pay.');
        }
        if ($dto->amountUgx !== $booking->amount_ugx) {
            $message = 'The amount due is UGX '.number_format($booking->amount_ugx).'.';
            throw new EntityValidationException($message, ['amount_ugx' => [$message]]);
        }

        DB::transaction(function () use ($booking, $dto) {
            $this->payments->recordForFacilityBooking($booking, $dto);
            $this->repository->update($booking, ['status' => FacilityBookingStatus::Confirmed->value, 'charge_mode' => ChargeMode::Paid->value]);
            $this->audit->record('facility_booking.paid', $booking, ['amount_ugx' => ['from' => null, 'to' => $dto->amountUgx]]);
        });

        return $this->find($id);
    }

    public function cancel(string $id): FacilityBooking
    {
        $booking = $this->find($id);
        if ($booking->status === FacilityBookingStatus::Cancelled) {
            throw new EntityValidationException('This booking is already cancelled.');
        }
        if ($booking->charge_mode === ChargeMode::Folio) {
            throw new EntityValidationException('This was added to the room bill. Remove the charge from the folio instead.');
        }

        return DB::transaction(function () use ($booking) {
            $updated = $this->repository->update($booking, ['status' => FacilityBookingStatus::Cancelled->value]);
            $this->audit->record('facility_booking.cancelled', $booking);

            return $updated;
        });
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $now = now('Africa/Kampala');

        return $this->repository->summary(
            $now->copy()->startOfDay()->utc()->toDateTimeString(),
            $now->copy()->endOfDay()->utc()->toDateTimeString(),
            $now->copy()->addDays(6)->endOfDay()->utc()->toDateTimeString(),
        );
    }
}
