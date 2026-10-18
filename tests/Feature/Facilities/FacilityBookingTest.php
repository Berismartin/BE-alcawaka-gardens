<?php

namespace Tests\Feature\Facilities;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\Rooms\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilityBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_pass_paid_at_desk_and_pay_later_event(): void
    {
        $this->travelTo(now('Africa/Kampala')->setTime(8, 0));
        $pool = Facility::factory()->create(['name' => 'Pool', 'access' => 'paid', 'price_ugx' => 20000, 'capacity' => 30]);
        $this->actingAsRole(UserRole::FrontDesk);
        $at = now('Africa/Kampala')->setTime(10, 0)->format('Y-m-d H:i');

        $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => $at, 'party_size' => 4, 'charge_mode' => 'paid', 'guest_name' => 'Namatovu K.', 'amount_ugx' => 80000,
        ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => $at, 'party_size' => 4, 'charge_mode' => 'paid', 'guest_name' => 'Namatovu K.', 'amount_ugx' => 80000, 'payment_method' => 'mtn_momo',
        ])->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.paid_with', 'MTN MoMo');

        $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => $at, 'party_size' => 40, 'charge_mode' => 'pay_later', 'guest_name' => 'Big group',
        ])->assertUnprocessable()->assertJsonValidationErrors('party_size');

        $later = $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => $at, 'party_size' => 2, 'charge_mode' => 'pay_later', 'guest_name' => 'Okello P.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.amount_ugx', 20000)->json('data.id');

        $this->idempotent()->postJson("/api/portal/facility-bookings/{$later}/pay", ['method' => 'cash', 'amount_ugx' => 20000])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');

        $this->getJson('/api/portal/facility-bookings/summary')->assertOk()
            ->assertJsonPath('data.visitors_today', 6)->assertJsonPath('data.income_today_ugx', 100000)->assertJsonPath('data.pending_payments', 0);
        $this->getJson('/api/portal/payments?q=Namatovu')->assertJsonPath('data.0.facility_booking.facility_name', 'Pool');

        // A facility with bookings keeps its history: it can be closed, not deleted.
        $this->actingAsRole(UserRole::Manager);
        $this->deleteJson("/api/portal/facilities/{$pool->id}")->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'has bookings on record'));
    }

    public function test_in_house_guest_charge_goes_on_the_room_bill(): void
    {
        $restaurant = Facility::factory()->create(['name' => 'Restaurant', 'access' => 'free_for_guests']);
        $room = Room::factory()->create();
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->idempotent()->postJson('/api/portal/reservations', [
            'check_in' => now('Africa/Kampala')->toDateString(), 'check_out' => now('Africa/Kampala')->addDay()->toDateString(),
            'adults' => 1, 'room_id' => $room->id, 'guest' => ['full_name' => 'Joseph Mugisha'],
        ])->json('data.id');
        $at = now('Africa/Kampala')->addHours(2)->format('Y-m-d H:i');

        // Not checked in yet: nothing can go on the bill.
        $this->idempotent()->postJson('/api/portal/facility-bookings', ['facility_id' => $restaurant->id, 'starts_at' => $at, 'party_size' => 2, 'charge_mode' => 'folio', 'reservation_id' => $id, 'amount_ugx' => 45000])
            ->assertUnprocessable()->assertJsonValidationErrors('reservation_id');

        $this->postJson("/api/portal/reservations/{$id}/check-in")->assertOk();
        $this->idempotent()->postJson('/api/portal/facility-bookings', ['facility_id' => $restaurant->id, 'starts_at' => $at, 'party_size' => 2, 'charge_mode' => 'folio', 'reservation_id' => $id, 'amount_ugx' => 45000])
            ->assertCreated()->assertJsonPath('data.reservation.room_number', $room->number);

        $this->getJson("/api/portal/reservations/{$id}")->assertJsonPath('data.charges.1.category', 'facility')->assertJsonPath('data.charges.1.amount_ugx', 45000);

        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson('/api/portal/facility-bookings')->assertForbidden();
    }
}
