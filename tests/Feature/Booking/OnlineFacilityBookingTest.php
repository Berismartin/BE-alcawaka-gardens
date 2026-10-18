<?php

namespace Tests\Feature\Booking;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\FacilityBookingConfirmedMail;
use App\Models\Facilities\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OnlineFacilityBookingTest extends TestCase
{
    use RefreshDatabase;

    private Facility $pool;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now('Africa/Kampala')->setTime(9, 0));
        $this->pool = Facility::factory()->create(['name' => 'Swimming pool', 'access' => 'paid', 'price_ugx' => 20000, 'capacity' => 30]);
    }

    private function book(array $overrides = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/facility-bookings', array_merge([
            'facility_id' => $this->pool->id,
            'date' => now('Africa/Kampala')->addDays(3)->toDateString(),
            'time' => '10:00',
            'party_size' => 4,
            'guest' => ['full_name' => 'Grace Achieng', 'phone' => '0782 555 010', 'email' => 'grace@example.com'],
            'payment_method' => 'mtn_momo',
        ], $overrides));
    }

    public function test_guest_books_a_day_pass_declines_then_pays_and_staff_see_it(): void
    {
        $res = $this->book()->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.amount_ugx', 80000) // a day pass is per person
            ->assertJsonPath('data.payment.amount_ugx', 80000)
            ->assertJsonPath('data.guest.phone', '+256782555010');
        [$id, $key] = [$res->json('data.id'), $res->json('data.key')];

        $this->getJson("/api/facility-bookings/{$id}?key=wrong")->assertNotFound();
        $this->getJson("/api/facility-bookings/not-a-uuid?key={$key}")->assertNotFound();
        $this->postJson("/api/facility-bookings/{$id}/simulate", ['key' => $key, 'outcome' => 'decline'])->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.payment.status', 'failed');
        $this->postJson("/api/facility-bookings/{$id}/pay", ['key' => $key])->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson("/api/facility-bookings/{$id}/pay", ['key' => $key, 'payment_method' => 'card'])->assertOk()->assertJsonPath('data.payment.status', 'pending');
        $this->postJson("/api/facility-bookings/{$id}/simulate", ['key' => $key, 'outcome' => 'approve'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.paid_ugx', 80000)->assertJsonPath('data.hold_expires_at', null);

        Mail::assertSent(FacilityBookingConfirmedMail::class, fn (FacilityBookingConfirmedMail $m) => $m->hasTo('grace@example.com') && str_contains($m->render(), 'Swimming pool'));
        $this->postJson("/api/facility-bookings/{$id}/pay", ['key' => $key, 'payment_method' => 'mtn_momo'])->assertUnprocessable();

        $this->actingAsRole(UserRole::FrontDesk);
        $this->getJson('/api/portal/facility-bookings')->assertOk()
            ->assertJsonPath('data.0.charge_mode', 'online')->assertJsonPath('data.0.status', 'confirmed')->assertJsonPath('data.0.paid_with', 'Card online');
    }

    public function test_event_space_is_one_price_and_rules_are_checked(): void
    {
        $garden = Facility::factory()->create(['name' => 'Gardens', 'access' => 'reservation', 'price_ugx' => 500000, 'capacity' => 150]);
        $this->book(['facility_id' => $garden->id, 'party_size' => 80])->assertCreated()->assertJsonPath('data.amount_ugx', 500000);

        $this->book(['party_size' => 31])->assertUnprocessable()->assertJsonValidationErrors('party_size');
        $this->book(['date' => now('Africa/Kampala')->toDateString(), 'time' => '08:00'])->assertUnprocessable()->assertJsonValidationErrors('time');
        $free = Facility::factory()->create(['access' => 'free_for_guests', 'price_ugx' => null]);
        $this->book(['facility_id' => $free->id])->assertUnprocessable()->assertJsonValidationErrors('facility_id');
        $this->book(['guest' => ['full_name' => 'A', 'phone' => '0700000000']])->assertUnprocessable()->assertJsonValidationErrors('guest.email');
    }

    public function test_unpaid_booking_is_cancelled_when_the_hold_runs_out(): void
    {
        $res = $this->book();
        $this->travel(11)->minutes();
        $this->getJson("/api/facility-bookings/{$res->json('data.id')}?key={$res->json('data.key')}")->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.payment.status', 'failed');
    }

    public function test_cash_reserves_now_to_pay_at_reception(): void
    {
        $res = $this->book(['payment_method' => 'cash'])->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.pay_at_venue', true)
            ->assertJsonPath('data.payment', null)
            ->assertJsonPath('data.hold_expires_at', null);
        Mail::assertSent(FacilityBookingConfirmedMail::class, fn (FacilityBookingConfirmedMail $m) => str_contains($m->render(), 'To pay at reception'));

        $this->travel(30)->minutes();
        $this->getJson("/api/facility-bookings/{$res->json('data.id')}?key={$res->json('data.key')}")->assertJsonPath('data.status', 'pending_payment');
        $this->postJson("/api/facility-bookings/{$res->json('data.id')}/pay", ['key' => $res->json('data.key'), 'payment_method' => 'mtn_momo'])->assertUnprocessable();

        // The desk takes the money as for any pay-later booking.
        $this->actingAsRole(UserRole::FrontDesk);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/portal/facility-bookings/{$res->json('data.id')}/pay", ['method' => 'cash', 'amount_ugx' => 80000])
            ->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_switching_to_cash_after_a_declined_payment(): void
    {
        $res = $this->book();
        [$id, $key] = [$res->json('data.id'), $res->json('data.key')];
        $this->postJson("/api/facility-bookings/{$id}/simulate", ['key' => $key, 'outcome' => 'decline']);
        $this->postJson("/api/facility-bookings/{$id}/pay", ['key' => $key, 'payment_method' => 'cash'])->assertOk()
            ->assertJsonPath('data.pay_at_venue', true)->assertJsonPath('data.hold_expires_at', null);
        Mail::assertSent(FacilityBookingConfirmedMail::class);
    }
}
