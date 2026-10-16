<?php

namespace App\Domains\Channels\Channel\Repositories;

use App\Domains\Reservations\Reservation\Enums\ReservationStatus;
use App\Models\Channels\Channel;
use App\Models\Reservations\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ChannelRepository implements ChannelRepositoryInterface
{
    /** @return list<string> */
    private static function holding(): array
    {
        return array_values(array_map(fn ($s) => $s->value, array_filter(ReservationStatus::cases(), fn ($s) => $s->holdsRoom())));
    }

    public function all(): Collection
    {
        return Channel::with('room:id,number,room_type_id', 'room.type:id,name')->orderBy('name')->get()->each->makeVisible('export_token');
    }

    public function findById(string $id): ?Channel
    {
        return Channel::with('room:id,number,room_type_id', 'room.type:id,name')->find($id)?->makeVisible('export_token');
    }

    public function findByToken(string $token): ?Channel
    {
        return Channel::with('room')->where('export_token', $token)->where('is_active', true)->first();
    }

    public function create(array $data): Channel
    {
        return $this->findById(Channel::create($data)->id);
    }

    public function update(Channel $channel, array $data): Channel
    {
        $channel->update($data);

        return $this->findById($channel->id);
    }

    public function delete(Channel $channel): void
    {
        $channel->delete();
    }

    public function blockingStays(Channel $channel): Collection
    {
        return Reservation::where('room_id', $channel->room_id)
            ->whereIn('status', self::holding())
            ->where('check_out', '>=', now('Africa/Kampala')->subDays(30)->toDateString())
            ->where(fn ($q) => $q->whereNull('channel_id')->orWhere('channel_id', '!=', $channel->id)) // don't echo the OTA's own bookings back
            ->get(['id', 'number', 'check_in', 'check_out']);
    }

    public function importedStays(Channel $channel): Collection
    {
        return Reservation::where('channel_id', $channel->id)
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('check_in', '>=', now('Africa/Kampala')->toDateString())
            ->get();
    }

    public function findImported(string $uid): ?Reservation
    {
        return Reservation::where('external_uid', $uid)->first();
    }

    public function soldByTypeAndNight(string $from, string $to): array
    {
        $rows = DB::table('reservation_nights')
            ->join('reservations', 'reservations.id', '=', 'reservation_nights.reservation_id')
            ->whereIn('reservations.status', self::holding())
            ->whereBetween('reservation_nights.night', [$from, $to])
            ->groupBy('reservations.room_type_id', 'reservation_nights.night')
            ->get(['reservations.room_type_id', 'reservation_nights.night', DB::raw('count(*) as sold')]);

        $sold = [];
        foreach ($rows as $row) {
            $sold[$row->room_type_id][substr($row->night, 0, 10)] = (int) $row->sold;
        }

        return $sold;
    }
}
