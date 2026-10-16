<?php

namespace Tests\Feature\Support;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Rooms\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_out_tells_housekeeping_and_clean_tells_front_desk(): void
    {
        $cleaner = User::factory()->create();
        $cleaner->forceFill(['role' => UserRole::Housekeeping])->save();
        $room = Room::factory()->create(['number' => '102']);
        $desk = $this->actingAsRole(UserRole::FrontDesk);

        $id = $this->idempotent()->postJson('/api/portal/reservations', [
            'check_in' => now('Africa/Kampala')->toDateString(), 'check_out' => now('Africa/Kampala')->addDay()->toDateString(),
            'adults' => 1, 'room_id' => $room->id, 'guest' => ['full_name' => 'Joseph Mugisha'], 'payment' => ['method' => 'cash', 'amount_ugx' => 180000],
        ])->json('data.id');
        $this->postJson("/api/portal/reservations/{$id}/check-in")->assertOk();
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/check-out")->assertOk();

        Sanctum::actingAs($cleaner);
        $this->getJson('/api/portal/notifications')->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.title', 'Room 102 needs cleaning');
        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'available'])->assertOk();
        $this->postJson('/api/portal/notifications/read')->assertOk();
        $this->getJson('/api/portal/notifications')->assertJsonPath('data.unread_count', 0);

        Sanctum::actingAs($desk);
        $this->getJson('/api/portal/notifications')->assertJsonPath('data.items.0.title', 'Room 102 is clean and ready');
    }

    public function test_search_respects_permissions(): void
    {
        $room = Room::factory()->create(['number' => '301']);
        $this->actingAsRole(UserRole::FrontDesk);
        $this->idempotent()->postJson('/api/portal/reservations', [
            'check_in' => now('Africa/Kampala')->toDateString(), 'check_out' => now('Africa/Kampala')->addDay()->toDateString(),
            'adults' => 1, 'room_id' => $room->id, 'guest' => ['full_name' => 'Amina Nakato'],
        ]);

        $this->getJson('/api/portal/search?q=nakato')->assertOk()
            ->assertJsonPath('data.guests.0.label', 'Amina Nakato')
            ->assertJsonPath('data.reservations.0.label', 'ARS-10001 · Amina Nakato');
        $this->getJson('/api/portal/search?q=a')->assertUnprocessable();

        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson('/api/portal/search?q=301')->assertOk()
            ->assertJsonCount(0, 'data.guests')->assertJsonCount(0, 'data.reservations')->assertJsonPath('data.rooms.0.label', 'Room 301');
    }
}
