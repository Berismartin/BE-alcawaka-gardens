<?php

namespace App\Domains\Reservations\Reservation\Services;

use App\Domains\Rates\SeasonalDiscount\Services\PricingService;
use App\Domains\Reservations\Reservation\DTOs\StayDTO;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\Room\Repositories\RoomRepositoryInterface;
use App\Exceptions\EntityValidationException;
use App\Models\Rooms\Room;
use Carbon\CarbonImmutable;

/** Which rooms are free for a stay, and what each would cost. */
class AvailabilityService
{
    public const MAX_NIGHTS = 60;

    public function __construct(
        private RoomRepositoryInterface $rooms,
        private ReservationRepositoryInterface $reservations,
        private PricingService $pricing,
    ) {}

    /** @return list<array{room: Room, nightly: array<string, int>, total_ugx: int}> */
    public function search(StayDTO $stay, ?string $exceptReservationId = null): array
    {
        $this->assertStay($stay);
        $taken = array_flip($this->reservations->takenRoomIds($stay->checkIn, $stay->checkOut, $exceptReservationId));

        $results = [];
        foreach ($this->rooms->all() as $room) {
            if (isset($taken[$room->id]) || $room->status->blocksBooking() || ! $room->type->is_active) {
                continue;
            }
            if ($stay->roomTypeId && $room->room_type_id !== $stay->roomTypeId) {
                continue;
            }
            if ($stay->guests() > $room->type->max_guests) {
                continue;
            }
            $nightly = $this->pricing->nightlyRates($room->type, $stay->checkIn, $stay->checkOut);
            $results[] = ['room' => $room, 'nightly' => $nightly, 'total_ugx' => array_sum($nightly)];
        }

        return $results;
    }

    public function assertStay(StayDTO $stay): void
    {
        $in = CarbonImmutable::parse($stay->checkIn);
        $out = CarbonImmutable::parse($stay->checkOut);
        if ($out->lte($in)) {
            throw new EntityValidationException('Check-out must be after check-in.', ['check_out' => ['Check-out must be after check-in.']]);
        }
        if ($in->diffInDays($out) > self::MAX_NIGHTS) {
            throw new EntityValidationException('A booking can be at most '.self::MAX_NIGHTS.' nights.', ['check_out' => ['A booking can be at most '.self::MAX_NIGHTS.' nights.']]);
        }
        if ($stay->adults < 1) {
            throw new EntityValidationException('At least one adult is needed.', ['adults' => ['At least one adult is needed.']]);
        }
    }
}
