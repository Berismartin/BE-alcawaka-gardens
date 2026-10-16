<?php

namespace Tests\Feature\Rooms;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sets_up_room_types_and_rooms(): void
    {
        $this->actingAsRole();

        $typeId = $this->postJson('/api/portal/room-types', [
            'name' => 'Deluxe Room', 'base_rate_ugx' => 260000, 'max_guests' => 3, 'features' => ['King bed', 'Balcony'],
        ])->assertCreated()->assertJsonPath('data.slug', 'deluxe-room')->json('data.id');

        $roomId = $this->postJson('/api/portal/rooms', ['number' => '105', 'room_type_id' => $typeId, 'floor' => 1])
            ->assertCreated()->assertJsonPath('data.status', 'available')->assertJsonPath('data.type.name', 'Deluxe Room')
            ->json('data.id');

        $this->postJson('/api/portal/rooms', ['number' => '105', 'room_type_id' => $typeId])->assertStatus(409);
        $this->getJson('/api/portal/room-types')->assertOk()->assertJsonPath('data.0.rooms_count', 1);
        $this->deleteJson("/api/portal/room-types/{$typeId}")->assertUnprocessable(); // still has rooms
        $this->getJson('/api/portal/rooms?filter[status]=available')->assertOk()->assertJsonPath('meta.total', 1);

        $this->deleteJson("/api/portal/rooms/{$roomId}")->assertOk();
        $this->deleteJson("/api/portal/room-types/{$typeId}")->assertOk();
    }

    public function test_front_desk_can_view_but_not_change_rooms(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $this->getJson('/api/portal/rooms')->assertOk();
        $this->postJson('/api/portal/room-types', ['name' => 'X', 'base_rate_ugx' => 1])->assertForbidden();
    }

    public function test_housekeeping_updates_room_status(): void
    {
        $room = Room::factory()->create(['status' => 'needs_cleaning']);
        $cleaner = $this->actingAsRole(UserRole::Housekeeping);

        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'available'])
            ->assertOk()->assertJsonPath('data.status', 'available')->assertJsonPath('data.status_changed_by.id', $cleaner->id);

        // An issue needs a description.
        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'under_maintenance'])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'under_maintenance', 'note' => 'Air conditioner repair'])
            ->assertOk()->assertJsonPath('data.status_note', 'Air conditioner repair');

        // Only check-in makes a room occupied.
        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'occupied'])->assertUnprocessable();

        $this->actingAsRole(UserRole::FrontDesk);
        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'available'])->assertForbidden();
    }

    public function test_seasonal_discounts_cannot_overlap(): void
    {
        $this->actingAsRole();
        $standard = RoomType::factory()->create();

        $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Festive season', 'starts_on' => '2026-12-15', 'ends_on' => '2027-01-05', 'percent' => 10])
            ->assertCreated()->assertJsonPath('data.room_type', null);

        // "All rooms" covers every type, so a type-specific discount inside it clashes.
        $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Xmas', 'starts_on' => '2026-12-20', 'ends_on' => '2026-12-27', 'percent' => 15, 'room_type_id' => $standard->id])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_on');

        $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Low season', 'starts_on' => '2027-04-01', 'ends_on' => '2027-04-30', 'percent' => 15, 'room_type_id' => $standard->id])
            ->assertCreated();

        $this->actingAsRole(UserRole::FrontDesk);
        $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Y', 'starts_on' => '2027-06-01', 'ends_on' => '2027-06-02', 'percent' => 5])->assertForbidden();
    }
}
