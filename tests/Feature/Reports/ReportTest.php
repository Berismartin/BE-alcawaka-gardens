<?php

namespace Tests\Feature\Reports;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_reports_add_up(): void
    {
        $this->travelTo(now('Africa/Kampala')->setTime(9, 0));
        $type = RoomType::factory()->create(['base_rate_ugx' => 100000]);
        $rooms = Room::factory()->count(4)->create(['room_type_id' => $type->id]);
        $today = now('Africa/Kampala')->toDateString();
        $tomorrow = now('Africa/Kampala')->addDay()->toDateString();

        $this->actingAsRole(UserRole::FrontDesk);
        $paid = $this->idempotent()->postJson('/api/portal/reservations', ['check_in' => $today, 'check_out' => $tomorrow, 'adults' => 1, 'room_id' => $rooms[0]->id, 'guest' => ['full_name' => 'Paid Guest'], 'payment' => ['method' => 'cash', 'amount_ugx' => 100000]])->json('data.id');
        $this->idempotent()->postJson('/api/portal/reservations', ['check_in' => $today, 'check_out' => $tomorrow, 'adults' => 1, 'room_id' => $rooms[1]->id, 'source' => 'phone', 'guest' => ['full_name' => 'Owing Guest']]);
        $this->postJson("/api/portal/reservations/{$paid}/charges", ['category' => 'bar', 'description' => 'Drinks', 'amount_ugx' => 25000]);

        $this->getJson('/api/portal/dashboard')->assertOk()
            ->assertJsonPath('data.occupancy.percent', 50)
            ->assertJsonCount(2, 'data.arrivals')
            ->assertJsonPath('data.open_balances.count', 2)
            ->assertJsonPath('data.open_balances.total', 125000)
            ->assertJsonPath('data.room_status.available', 4);

        $this->getJson("/api/portal/reports?period=daily&date={$today}")->assertForbidden(); // front desk

        $this->actingAsRole(UserRole::Owner);
        $r = $this->getJson("/api/portal/reports?period=daily&date={$today}")->assertOk()
            ->assertJsonPath('data.occupancy_percent', 50)
            ->assertJsonPath('data.income_ugx', 100000)
            ->assertJsonPath('data.bookings', 2)
            ->assertJsonCount(7, 'data.series')
            ->json('data');
        $sources = collect($r['revenue_by_source'])->keyBy('source');
        $this->assertSame(100000, $sources['front_desk']['amount_ugx']);
        $this->assertSame(100000, $sources['phone']['amount_ugx']);
        $this->assertSame(25000, $sources['services']['amount_ugx']);
        $this->assertSame(100000, end($r['series'])['income_ugx']);

        $this->getJson("/api/portal/reports?period=monthly&date={$today}")->assertOk()->assertJsonCount(12, 'data.series');
        $this->getJson("/api/portal/reports?period=weekly&date={$today}")->assertUnprocessable();
        $csv = $this->get("/api/portal/reports/export?period=monthly&date={$today}")->assertOk()->streamedContent();
        $this->assertStringContainsString('"Restaurant and services",25000', $csv);
    }
}
