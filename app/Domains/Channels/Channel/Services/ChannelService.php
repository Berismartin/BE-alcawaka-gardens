<?php

namespace App\Domains\Channels\Channel\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Channels\Channel\DTOs\ChannelDTO;
use App\Domains\Channels\Channel\Repositories\ChannelRepositoryInterface;
use App\Domains\Channels\Channel\ValueObjects\Ical;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Domains\Rooms\Room\Repositories\RoomRepositoryInterface;
use App\Domains\Rooms\RoomType\Repositories\RoomTypeRepositoryInterface;
use App\Exceptions\ConflictException;
use App\Exceptions\EntityNotFoundException;
use App\Models\Channels\Channel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Channel manager (B09), iCal first: each OTA listing (one room) gets our feed
 * of blocked nights, and we pull its feed of bookings. Direct OTA APIs come later.
 */
class ChannelService
{
    public function __construct(
        private ChannelRepositoryInterface $repository,
        private ReservationService $reservations,
        private RoomRepositoryInterface $rooms,
        private RoomTypeRepositoryInterface $roomTypes,
        private AuditLogger $audit,
    ) {}

    /** @return Collection<int, Channel> */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(string $id): Channel
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Channel', $id);
    }

    public function create(ChannelDTO $dto): Channel
    {
        return DB::transaction(function () use ($dto) {
            $channel = $this->repository->create([...$dto->toArray(), 'export_token' => Str::random(40)]);
            $this->audit->record('channel.created', $channel, ['name' => ['from' => null, 'to' => $dto->name]]);

            return $channel;
        });
    }

    public function update(string $id, ChannelDTO $dto): Channel
    {
        $channel = $this->find($id);
        $before = $channel->only(['name', 'room_id', 'import_url', 'is_active']);

        return DB::transaction(function () use ($channel, $dto, $before) {
            $updated = $this->repository->update($channel, $dto->toArray());
            $this->audit->record('channel.updated', $updated, AuditLogger::diff($before, $dto->toArray()));

            return $updated;
        });
    }

    public function delete(string $id): void
    {
        $channel = $this->find($id);
        DB::transaction(function () use ($channel) {
            $this->repository->delete($channel);
            $this->audit->record('channel.deleted', $channel, ['name' => ['from' => $channel->name, 'to' => null]]);
        });
    }

    /** "Sync now": pull every active feed. One failing feed never stops the others. */
    public function syncAll(): Collection
    {
        foreach ($this->repository->all()->where('is_active', true) as $channel) {
            $this->sync($channel);
        }

        return $this->list();
    }

    public function syncOne(string $id): Channel
    {
        return $this->sync($this->find($id));
    }

    /** The public .ics feed an OTA polls: blocked nights only, no guest details. */
    public function exportFeed(string $token): string
    {
        $channel = $this->repository->findByToken($token) ?? throw new EntityNotFoundException('Calendar', 'feed');
        $events = $this->repository->blockingStays($channel)->map(fn ($r) => [
            'uid' => $r->id.'@alcawaka-gardens',
            'start' => $r->check_in->toDateString(),
            'end' => $r->check_out->toDateString(),
            'summary' => 'Not available',
        ]);

        return Ical::build("Room {$channel->room->number}", $events);
    }

    /**
     * Rooms left to sell per room type per night, as every channel sees it.
     *
     * @return array{nights: list<string>, types: list<array{id: string, name: string, rooms: int, left: list<int>}>}
     */
    public function availability(string $from, int $days): array
    {
        $start = CarbonImmutable::parse($from);
        $nights = collect(range(0, $days - 1))->map(fn ($i) => $start->addDays($i)->toDateString())->all();
        $sold = $this->repository->soldByTypeAndNight($nights[0], end($nights));
        $sellable = $this->rooms->all()->reject(fn ($r) => $r->status->blocksBooking())->countBy('room_type_id');

        $types = $this->roomTypes->all(activeOnly: true)->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'rooms' => (int) ($sellable[$t->id] ?? 0),
            'left' => array_map(fn ($n) => max(0, (int) ($sellable[$t->id] ?? 0) - ($sold[$t->id][$n] ?? 0)), $nights),
        ])->values()->all();

        return ['nights' => $nights, 'types' => $types];
    }

    private function sync(Channel $channel): Channel
    {
        if (! $channel->import_url) {
            return $this->repository->update($channel, ['last_synced_at' => now(), 'last_sync_status' => 'ok', 'last_sync_message' => 'Export only: no booking feed to import.']);
        }

        try {
            $response = Http::timeout(15)->get($channel->import_url);
            if (! $response->successful()) {
                throw new \RuntimeException("The channel answered with HTTP {$response->status()}.");
            }
            $events = Ical::parse($response->body());
        } catch (Throwable $e) {
            $message = $e instanceof \RuntimeException ? $e->getMessage() : "Couldn't reach the channel's calendar link.";

            return $this->repository->update($channel, ['last_synced_at' => now(), 'last_sync_status' => 'failed', 'last_sync_message' => mb_substr($message, 0, 255)]);
        }

        $today = now('Africa/Kampala')->toDateString();
        $added = $conflicts = $cancelled = 0;
        $seen = [];
        foreach ($events as $event) {
            if ($event['end'] <= $today) {
                continue;
            }
            $uid = $channel->id.':'.$event['uid'];
            $seen[$uid] = true;
            if ($this->repository->findImported($uid)) {
                continue;
            }
            try {
                $this->reservations->importFromChannel($channel, $uid, $event['summary'], max($event['start'], $today), $event['end']);
                $added++;
            } catch (ConflictException) {
                $conflicts++;
            }
        }
        // A booking that disappeared from the OTA feed was cancelled there.
        foreach ($this->repository->importedStays($channel) as $stay) {
            if (! isset($seen[$stay->external_uid])) {
                $this->reservations->cancel($stay->id, "Cancelled on {$channel->name}");
                $cancelled++;
            }
        }

        $summary = "{$added} new, {$cancelled} cancelled".($conflicts ? ", {$conflicts} clashed with existing bookings" : '');
        $this->audit->record('channel.synced', $channel, ['result' => ['from' => null, 'to' => $summary]]);

        return $this->repository->update($channel, [
            'last_synced_at' => now(),
            'last_sync_status' => $conflicts ? 'failed' : 'ok',
            'last_sync_message' => $conflicts ? "{$summary}. Check the calendar." : $summary,
        ]);
    }
}
