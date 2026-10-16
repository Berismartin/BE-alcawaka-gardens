<?php

namespace App\Domains\Reservations\Reservation\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Guests\Guest\DTOs\GuestDTO;
use App\Domains\Guests\Guest\Services\GuestService;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Payments\Payment\Services\PaymentService;
use App\Domains\Rates\SeasonalDiscount\Services\PricingService;
use App\Domains\Reservations\Reservation\DTOs\CreateReservationDTO;
use App\Domains\Reservations\Reservation\DTOs\StayDTO;
use App\Domains\Reservations\Reservation\DTOs\UpdateReservationDTO;
use App\Domains\Reservations\Reservation\Enums\ChargeCategory;
use App\Domains\Reservations\Reservation\Enums\ReservationSource;
use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\Room\Enums\RoomStatus;
use App\Domains\Rooms\Room\Services\RoomService;
use App\Exceptions\ConflictException;
use App\Exceptions\EntityNotFoundException;
use App\Exceptions\EntityValidationException;
use App\Models\Channels\Channel;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private ReservationRepositoryInterface $repository,
        private AvailabilityService $availability,
        private PricingService $pricing,
        private RoomService $rooms,
        private GuestService $guests,
        private PaymentService $payments,
        private AuditLogger $audit,
        private NotificationService $notifications,
    ) {}

    public static function today(): string
    {
        return now('Africa/Kampala')->toDateString();
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): Reservation
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Reservation', $id);
    }

    public function create(CreateReservationDTO $dto): Reservation
    {
        $stay = $dto->stay;
        if ($stay->checkIn < self::today()) {
            throw new EntityValidationException("Check-in can't be in the past.", ['check_in' => ["Check-in can't be in the past."]]);
        }
        if (! $dto->guestId && ! $dto->guest) {
            throw new EntityValidationException('Add the guest’s details.', ['guest.full_name' => ['Enter the guest’s name.']]);
        }
        $room = $this->bookableRoom($dto->roomId, $stay);

        $id = DB::transaction(function () use ($dto, $stay, $room) {
            $guest = $dto->guestId ? $this->guests->find($dto->guestId) : $this->guests->findOrCreate($dto->guest);
            $reservation = $this->repository->create([
                'number' => $this->repository->nextNumber(),
                'guest_id' => $guest->id,
                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'check_in' => $stay->checkIn,
                'check_out' => $stay->checkOut,
                'adults' => $stay->adults,
                'children' => $stay->children,
                'status' => ReservationStatus::Confirmed->value,
                'source' => $dto->source->value,
                'special_requests' => $dto->specialRequests,
                'created_by' => auth()->id(),
            ]);
            $rates = $this->pricing->nightlyRates($room->type, $stay->checkIn, $stay->checkOut);
            $this->holdNights($reservation, $room, $rates);
            $this->repository->addCharge($reservation, $this->roomCharge($rates, $stay->checkIn));
            $this->audit->record('reservation.created', $reservation, [
                'room' => ['from' => null, 'to' => $room->number],
                'source' => ['from' => null, 'to' => $dto->source->value],
            ]);
            if ($dto->payment) {
                $this->payments->recordForReservation($reservation, $dto->payment);
            }
            $this->notifications->notify('reservations.manage', new PortalNotification(
                "New booking {$reservation->reference()}",
                "{$guest->full_name} · Room {$room->number} · ".CarbonImmutable::parse($stay->checkIn)->format('D j M').' – '.CarbonImmutable::parse($stay->checkOut)->format('D j M'),
                "/portal/reservations/{$reservation->id}",
                'i-lucide-calendar-plus',
            ));

            return $reservation->id;
        });

        return $this->find($id);
    }

    /**
     * A booking that arrived from an OTA calendar feed. Uses the same night lock as
     * the desk, so a clash throws ConflictException instead of double-booking.
     */
    public function importFromChannel(Channel $channel, string $uid, string $summary, string $checkIn, string $checkOut): Reservation
    {
        $room = $this->rooms->find($channel->room_id);

        $id = DB::transaction(function () use ($channel, $uid, $summary, $checkIn, $checkOut, $room) {
            $guest = $this->guests->create(new GuestDTO(fullName: mb_substr($summary !== '' ? $summary : "{$channel->name} guest", 0, 120), notes: "Imported from {$channel->name}"));
            $reservation = $this->repository->create([
                'number' => $this->repository->nextNumber(),
                'guest_id' => $guest->id,
                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => 1,
                'status' => ReservationStatus::Confirmed->value,
                'source' => ReservationSource::Ota->value,
                'external_uid' => $uid,
                'channel_id' => $channel->id,
            ]);
            $rates = $this->pricing->nightlyRates($room->type, $checkIn, $checkOut);
            $this->holdNights($reservation, $room, $rates);
            $this->repository->addCharge($reservation, $this->roomCharge($rates, $checkIn));
            $this->audit->record('reservation.created', $reservation, [
                'room' => ['from' => null, 'to' => $room->number],
                'source' => ['from' => null, 'to' => $channel->name],
            ]);

            return $reservation->id;
        });

        return $this->find($id);
    }

    /** Change dates, room, party size or requests. Changing the stay re-checks availability and re-prices it. */
    public function update(string $id, UpdateReservationDTO $dto): Reservation
    {
        $reservation = $this->find($id);
        $data = $dto->attributes;

        if ($dto->changesStay()) {
            if (! $reservation->status->isEditable()) {
                throw new EntityValidationException("A booking that is {$reservation->status->label()} can't change dates or room.");
            }
            $stay = new StayDTO(
                checkIn: $data['check_in'] ?? $reservation->check_in->toDateString(),
                checkOut: $data['check_out'] ?? $reservation->check_out->toDateString(),
                adults: (int) ($data['adults'] ?? $reservation->adults),
                children: (int) ($data['children'] ?? $reservation->children),
            );
            if ($stay->checkIn < self::today() && $stay->checkIn !== $reservation->check_in->toDateString()) {
                throw new EntityValidationException("Check-in can't be moved into the past.", ['check_in' => ["Check-in can't be moved into the past."]]);
            }
            $room = $this->bookableRoom($data['room_id'] ?? $reservation->room_id, $stay, $reservation->id);
        }

        $before = $reservation->only(['room_id', 'check_in', 'check_out', 'adults', 'children', 'special_requests']);
        $before['check_in'] = $reservation->check_in->toDateString();
        $before['check_out'] = $reservation->check_out->toDateString();

        DB::transaction(function () use ($reservation, $data, $dto, $before) {
            if ($dto->changesStay()) {
                $room = $this->rooms->find($data['room_id'] ?? $reservation->room_id);
                $checkIn = $data['check_in'] ?? $before['check_in'];
                $checkOut = $data['check_out'] ?? $before['check_out'];
                $this->repository->releaseNights($reservation);
                $rates = $this->pricing->nightlyRates($room->type, $checkIn, $checkOut);
                $this->holdNights($reservation, $room, $rates);
                $data['room_type_id'] = $room->room_type_id;
                $roomCharge = $reservation->charges->firstWhere('category', ChargeCategory::Room);
                $roomCharge?->update($this->roomCharge($rates, $checkIn));
            }
            $this->repository->update($reservation, $data);
            $this->audit->record('reservation.updated', $reservation, AuditLogger::diff($before, $dto->attributes));
        });

        return $this->find($id);
    }

    public function checkIn(string $id): Reservation
    {
        $reservation = $this->find($id);
        $this->assertTransition($reservation, ReservationStatus::CheckedIn);
        if ($reservation->check_in->toDateString() > self::today()) {
            throw new EntityValidationException('This guest arrives on '.$reservation->check_in->format('D j M').'. Check-in opens on the arrival day.');
        }
        $room = $reservation->room;
        if ($room->status !== RoomStatus::Available) {
            throw new EntityValidationException("Room {$room->number} is {$room->status->phrase()}. Mark it clean, or move the guest to another room first.");
        }

        DB::transaction(function () use ($reservation, $room) {
            $this->repository->update($reservation, ['status' => ReservationStatus::CheckedIn->value, 'checked_in_at' => now()]);
            $this->rooms->markForStay($room, RoomStatus::Occupied);
            $this->audit->record('reservation.checked_in', $reservation, ['room' => ['from' => null, 'to' => $room->number]]);
        });

        return $this->find($id);
    }

    /** The bill must be settled first. The room goes to housekeeping as "Needs cleaning". */
    public function checkOut(string $id): Reservation
    {
        $reservation = $this->find($id);
        $this->assertTransition($reservation, ReservationStatus::CheckedOut);
        $balance = $reservation->balance();
        if ($balance > 0) {
            throw new EntityValidationException('UGX '.number_format($balance).' is still due. Record the payment before checking out.');
        }

        DB::transaction(function () use ($reservation) {
            // Leaving early frees the remaining nights for resale; the bill stays as agreed.
            $tomorrow = CarbonImmutable::parse(self::today())->addDay()->toDateString();
            if ($reservation->check_out->toDateString() > $tomorrow) {
                $this->repository->releaseNights($reservation, $tomorrow);
            }
            $this->repository->update($reservation, ['status' => ReservationStatus::CheckedOut->value, 'checked_out_at' => now()]);
            $this->rooms->markForStay($reservation->room, RoomStatus::NeedsCleaning);
            $this->audit->record('reservation.checked_out', $reservation, ['room' => ['from' => null, 'to' => $reservation->room->number]]);
            $this->notifications->notify('housekeeping.manage', new PortalNotification(
                "Room {$reservation->room->number} needs cleaning",
                "{$reservation->guest->full_name} just checked out.",
                '/portal/housekeeping',
                'i-lucide-brush-cleaning',
            ));
        });

        return $this->find($id);
    }

    public function cancel(string $id, string $reason): Reservation
    {
        $reservation = $this->find($id);
        $this->assertTransition($reservation, ReservationStatus::Cancelled);

        DB::transaction(function () use ($reservation, $reason) {
            $this->repository->releaseNights($reservation);
            $this->repository->update($reservation, ['status' => ReservationStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            $this->audit->record('reservation.cancelled', $reservation, ['reason' => ['from' => null, 'to' => $reason]]);
            $this->notifications->notify('reservations.manage', new PortalNotification(
                "Booking {$reservation->reference()} cancelled",
                "{$reservation->guest->full_name} · Room {$reservation->room->number}. {$reason}",
                "/portal/reservations/{$reservation->id}",
                'i-lucide-calendar-x',
            ));
        });

        return $this->find($id);
    }

    public function markNoShow(string $id): Reservation
    {
        $reservation = $this->find($id);
        $this->assertTransition($reservation, ReservationStatus::NoShow);
        if ($reservation->check_in->toDateString() > self::today()) {
            throw new EntityValidationException("The guest isn't due until ".$reservation->check_in->format('D j M').'.');
        }

        DB::transaction(function () use ($reservation) {
            $this->repository->releaseNights($reservation);
            $this->repository->update($reservation, ['status' => ReservationStatus::NoShow->value]);
            $this->audit->record('reservation.no_show', $reservation);
        });

        return $this->find($id);
    }

    /** Scheduled: unpaid website holds give their room back. */
    public function expireHolds(): int
    {
        $expired = $this->repository->expiredHolds();
        foreach ($expired as $reservation) {
            DB::transaction(function () use ($reservation) {
                $this->repository->releaseNights($reservation);
                $this->repository->update($reservation, ['status' => ReservationStatus::Expired->value]);
                $this->audit->record('reservation.expired', $reservation);
            });
        }

        return $expired->count();
    }

    /** @return array{rooms: Collection<int, Room>, bookings: Collection<int, Reservation>} */
    public function calendar(string $from, int $days): array
    {
        $to = CarbonImmutable::parse($from)->addDays($days)->toDateString();

        return ['rooms' => $this->rooms->all(), 'bookings' => $this->repository->inRange($from, $to)];
    }

    private function bookableRoom(string $roomId, StayDTO $stay, ?string $exceptReservationId = null): Room
    {
        $this->availability->assertStay($stay);
        $room = $this->rooms->find($roomId);
        if ($room->status->blocksBooking()) {
            throw new EntityValidationException("Room {$room->number} is {$room->status->phrase()} and can't be booked.", ['room_id' => ["Room {$room->number} is {$room->status->phrase()}."]]);
        }
        if ($stay->guests() > $room->type->max_guests) {
            $message = "Room {$room->number} sleeps at most {$room->type->max_guests}.";
            throw new EntityValidationException($message, ['adults' => [$message]]);
        }
        if (in_array($room->id, $this->repository->takenRoomIds($stay->checkIn, $stay->checkOut, $exceptReservationId), true)) {
            throw $this->roomTaken($room);
        }

        return $room;
    }

    /** @param array<string, int> $rates */
    private function holdNights(Reservation $reservation, Room $room, array $rates): void
    {
        try {
            $this->repository->insertNights($reservation, $room->id, $rates);
        } catch (UniqueConstraintViolationException) {
            // Someone booked the same room a moment earlier: the unique index caught it.
            throw $this->roomTaken($room);
        }
    }

    private function roomTaken(Room $room): ConflictException
    {
        $message = "Room {$room->number} is no longer free for those dates.";

        return new ConflictException($message, ['room_id' => [$message]]);
    }

    /**
     * @param  array<string, int>  $rates
     * @return array<string, mixed>
     */
    private function roomCharge(array $rates, string $checkIn): array
    {
        $nights = count($rates);
        $total = array_sum($rates);
        $same = count(array_unique($rates)) === 1;
        $description = $same
            ? "Room charge · {$nights} ".($nights === 1 ? 'night' : 'nights').' × '.number_format(reset($rates))
            : "Room charge · {$nights} nights (seasonal rates)";

        return [
            'category' => ChargeCategory::Room->value,
            'description' => $description,
            'amount_ugx' => $total,
            'posted_on' => $checkIn,
            'created_by' => auth()->id(),
        ];
    }

    private function assertTransition(Reservation $reservation, ReservationStatus $next): void
    {
        if (! $reservation->status->canTransitionTo($next)) {
            throw new EntityValidationException("A booking that is {$reservation->status->label()} can't be marked {$next->label()}.");
        }
    }
}
