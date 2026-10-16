<?php

namespace Tests\Feature\Channels;

use App\Domains\Channels\Channel\ValueObjects\Ical;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\Rooms\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    private function feed(array $events): string
    {
        return Ical::build('OTA', array_map(fn ($e) => ['uid' => $e[0], 'start' => $e[1], 'end' => $e[2], 'summary' => $e[3] ?? 'Reserved'], $events));
    }

    public function test_ical_round_trip(): void
    {
        $events = Ical::parse("BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:abc\r\nDTSTART;VALUE=DATE:20261112\r\nDTEND;VALUE=DATE:20261114\r\nSUMMARY:Jane\r\n  Doe\r\nEND:VEVENT\r\nEND:VCALENDAR");
        $this->assertSame([['uid' => 'abc', 'summary' => 'Jane Doe', 'start' => '2026-11-12', 'end' => '2026-11-14']], $events);
    }

    public function test_sync_imports_blocks_and_cancels(): void
    {
        $this->actingAsRole();
        $room = Room::factory()->create(['number' => '104']);
        $d = fn ($n) => now('Africa/Kampala')->addDays($n)->toDateString();

        $channelId = $this->postJson('/api/portal/channels', ['name' => 'Booking site', 'room_id' => $room->id, 'import_url' => 'https://ota.test/104.ics'])
            ->assertCreated()->json('data.id');

        Http::fake(['ota.test/*' => Http::sequence()
            ->push($this->feed([['b1', $d(1), $d(3), 'Kizza B.'], ['old', $d(-5), $d(-3)]]))
            ->push($this->feed([]))
            ->push('nope', 500)]);

        $this->postJson('/api/portal/channels/sync')->assertOk()->assertJsonPath('data.0.last_sync_message', '1 new, 0 cancelled');
        $this->getJson('/api/portal/reservations?filter[source]=ota')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.guest.full_name', 'Kizza B.');
        $this->getJson("/api/portal/availability?check_in={$d(1)}&check_out={$d(2)}")->assertJsonCount(0, 'data'); // the OTA booking blocks it
        $this->getJson("/api/portal/channels/availability?from={$d(1)}&days=3")->assertJsonPath('data.types.0.left', [0, 0, 1]);

        // Our feed for the OTA leaves out its own booking (no echo).
        $exportUrl = $this->getJson('/api/portal/channels')->json('data.0.export_url');
        $this->get(parse_url($exportUrl, PHP_URL_PATH))->assertOk()->assertDontSee('BEGIN:VEVENT');

        $this->postJson('/api/portal/channels/sync')->assertJsonPath('data.0.last_sync_message', '0 new, 1 cancelled');
        $this->postJson('/api/portal/channels/sync')->assertJsonPath('data.0.last_sync_status', 'failed');

        // A desk booking shows up in the export feed.
        $this->idempotent()->postJson('/api/portal/reservations', ['check_in' => $d(0), 'check_out' => $d(2), 'adults' => 1, 'room_id' => $room->id, 'guest' => ['full_name' => 'Walk In']])->assertCreated();
        $this->get(parse_url($exportUrl, PHP_URL_PATH))->assertOk()->assertSee('DTSTART;VALUE=DATE:'.str_replace('-', '', $d(0)), false)->assertDontSee('Walk In');

        $this->deleteJson("/api/portal/channels/{$channelId}")->assertOk();
        $this->get(parse_url($exportUrl, PHP_URL_PATH))->assertNotFound();

        $this->actingAsRole(UserRole::FrontDesk);
        $this->getJson('/api/portal/channels')->assertForbidden();
    }
}
