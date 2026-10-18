<?php

namespace App\Domains\Guests\Guest\Repositories;

use App\Models\Guests\Guest;
use App\Models\Reservations\Reservation;
use App\Support\Query\AppliesListQuery;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GuestRepository implements GuestRepositoryInterface
{
    use AppliesListQuery;

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        $base = Guest::query()
            ->withCount('reservations')
            ->addSelect(['last_stay_on' => Reservation::select('check_in')->whereColumn('guest_id', 'guests.id')->latest('check_in')->limit(1)]);

        return $this->paginateList($base, $query, ['full_name', 'phone', 'email', 'id_number'], defaultSort: 'full_name');
    }

    public function findById(string $id): ?Guest
    {
        return Guest::with(['documents', 'reservations' => fn ($q) => $q->with('room:id,number')->limit(20)])
            ->withCount('reservations')
            ->find($id);
    }

    public function findByPhone(string $phone): ?Guest
    {
        return Guest::where('phone', $phone)->first();
    }

    public function findByPhoneAndEmail(string $phone, string $email): ?Guest
    {
        return Guest::where('phone', $phone)->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    public function hasReservations(Guest $guest): bool
    {
        return $guest->reservations()->exists();
    }

    public function create(array $data): Guest
    {
        return Guest::create($data);
    }

    public function update(Guest $guest, array $data): Guest
    {
        $guest->update($data);

        return $this->findById($guest->id);
    }

    public function delete(Guest $guest): void
    {
        $guest->delete();
    }
}
