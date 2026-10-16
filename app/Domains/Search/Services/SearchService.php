<?php

namespace App\Domains\Search\Services;

use App\Domains\Guests\Guest\Repositories\GuestRepositoryInterface;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\Room\Repositories\RoomRepositoryInterface;
use App\Models\User;
use App\Support\Query\ListQuery;

/** Ctrl/Cmd+K: guests, bookings and rooms the user is allowed to see. */
class SearchService
{
    public function __construct(
        private GuestRepositoryInterface $guests,
        private ReservationRepositoryInterface $reservations,
        private RoomRepositoryInterface $rooms,
    ) {}

    /** @return array<string, list<array<string, mixed>>> */
    public function search(User $user, string $term): array
    {
        $results = ['guests' => [], 'reservations' => [], 'rooms' => []];
        $q = new ListQuery(perPage: 10, search: $term);

        if ($user->role->can('guests.view')) {
            $results['guests'] = collect($this->guests->paginate(new ListQuery(perPage: 10, search: $term))->items())
                ->take(5)->map(fn ($g) => ['id' => $g->id, 'label' => $g->full_name, 'suffix' => $g->phone])->values()->all();
        }
        if ($user->role->can('reservations.view')) {
            $results['reservations'] = $this->reservations->search($term, 5)
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'label' => $r->reference().' · '.$r->guest->full_name,
                    'suffix' => 'Room '.$r->room->number.' · '.$r->check_in->format('j M'),
                ])->values()->all();
        }
        if ($user->role->can('rooms.view')) {
            $results['rooms'] = collect($this->rooms->paginate($q)->items())
                ->take(5)->map(fn ($room) => ['id' => $room->id, 'label' => 'Room '.$room->number, 'suffix' => $room->type->name, 'status' => $room->status->value])->values()->all();
        }

        return $results;
    }
}
