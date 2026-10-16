<?php

namespace App\Domains\Channels\Channel\Repositories;

use App\Models\Channels\Channel;
use App\Models\Reservations\Reservation;
use Illuminate\Database\Eloquent\Collection;

interface ChannelRepositoryInterface
{
    /** @return Collection<int, Channel> */
    public function all(): Collection;

    public function findById(string $id): ?Channel;

    public function findByToken(string $token): ?Channel;

    /** @param array<string, mixed> $data */
    public function create(array $data): Channel;

    /** @param array<string, mixed> $data */
    public function update(Channel $channel, array $data): Channel;

    public function delete(Channel $channel): void;

    /** @return Collection<int, Reservation> stays in the room that the feed should show as blocked */
    public function blockingStays(Channel $channel): Collection;

    /** @return Collection<int, Reservation> future bookings that came from this channel */
    public function importedStays(Channel $channel): Collection;

    public function findImported(string $uid): ?Reservation;

    /** @return array<string, array<string, int>> room type id => night => rooms sold */
    public function soldByTypeAndNight(string $from, string $to): array;
}
