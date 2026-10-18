<?php

namespace Tests\Feature\Rooms;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Rooms\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_a_room_logs_an_issue_and_reopening_marks_it_fixed(): void
    {
        $room = Room::factory()->create(['number' => '201', 'status' => 'available']);
        $cleaner = $this->actingAsRole(UserRole::Housekeeping);

        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'under_maintenance', 'note' => 'Air conditioner repair'])->assertOk();
        $this->getJson('/api/portal/maintenance-issues?filter[state]=open')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.description', 'Air conditioner repair')
            ->assertJsonPath('data.0.closes_room', true)
            ->assertJsonPath('data.0.reported_by', $cleaner->name);

        $this->patchJson("/api/portal/rooms/{$room->id}/status", ['status' => 'available'])->assertOk();
        $this->getJson('/api/portal/maintenance-issues?filter[state]=open')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/portal/maintenance-issues?filter[state]=fixed')->assertJsonPath('data.0.resolution', 'Room marked clean and ready');
    }

    public function test_front_desk_reports_a_minor_issue_in_an_occupied_room_and_housekeeping_fixes_it(): void
    {
        $room = Room::factory()->create(['number' => '105', 'status' => 'occupied']);
        $this->actingAsRole(UserRole::FrontDesk);
        $this->postJson('/api/portal/maintenance-issues', ['room_id' => $room->id])->assertUnprocessable()->assertJsonValidationErrors('description');
        $id = $this->postJson('/api/portal/maintenance-issues', ['room_id' => $room->id, 'description' => 'Shower drips'])
            ->assertCreated()->assertJsonPath('data.closes_room', false)->assertJsonPath('data.room.number', '105')->json('data.id');
        $this->assertSame('occupied', $room->fresh()->status->value);
        $this->postJson("/api/portal/maintenance-issues/{$id}/resolve")->assertForbidden();

        $this->actingAsRole(UserRole::Housekeeping);
        $this->postJson("/api/portal/maintenance-issues/{$id}/resolve", ['resolution' => 'New washer fitted'])->assertOk()
            ->assertJsonPath('data.resolution', 'New washer fitted')->assertJsonPath('data.room.status', 'occupied');
        $this->postJson("/api/portal/maintenance-issues/{$id}/resolve")->assertUnprocessable();
    }

    public function test_fixing_the_last_closing_issue_sends_the_room_to_housekeeping(): void
    {
        $room = Room::factory()->create(['number' => '301', 'status' => 'available']);
        $this->actingAsRole(UserRole::Manager);
        $this->postJson('/api/portal/maintenance-issues', ['room_id' => $room->id, 'description' => 'Window broken', 'close_as' => 'available'])->assertUnprocessable();
        $first = $this->postJson('/api/portal/maintenance-issues', ['room_id' => $room->id, 'description' => 'Window broken', 'close_as' => 'out_of_order'])
            ->assertCreated()->assertJsonPath('data.closes_room', true)->assertJsonPath('data.room.status', 'out_of_order')->json('data.id');
        $this->postJson('/api/portal/maintenance-issues', ['room_id' => $room->id, 'description' => 'Light fitting loose'])->assertCreated();

        $this->postJson("/api/portal/maintenance-issues/{$first}/resolve")->assertOk()->assertJsonPath('data.room.status', 'needs_cleaning');
        $this->getJson('/api/portal/maintenance-issues?filter[state]=open')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.description', 'Light fitting loose');
    }
}
