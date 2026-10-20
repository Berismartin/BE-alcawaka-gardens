<?php

namespace Tests\Feature\Reservations;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\ReceiptMail;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Africa/Kampala')->setTime(9, 0));
        $type = RoomType::factory()->create(['name' => 'Standard Room', 'base_rate_ugx' => 180000, 'max_guests' => 2]);
        $this->room = Room::factory()->create(['number' => '103', 'room_type_id' => $type->id]);
    }

    private function day(int $offset): string
    {
        return now('Africa/Kampala')->addDays($offset)->toDateString();
    }

    /** @param array<string, mixed> $overrides */
    private function book(array $overrides = []): TestResponse
    {
        return $this->idempotent()->postJson('/api/portal/reservations', array_merge([
            'check_in' => $this->day(0),
            'check_out' => $this->day(2),
            'adults' => 2,
            'room_id' => $this->room->id,
            'guest' => ['full_name' => 'Moses Ssali', 'phone' => '0772 000 111'],
        ], $overrides));
    }

    public function test_availability_prices_each_night_with_discounts(): void
    {
        $this->actingAsRole();
        $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Promo', 'starts_on' => $this->day(1), 'ends_on' => $this->day(1), 'percent' => 10])->assertCreated();

        $this->getJson('/api/portal/availability?check_in='.$this->day(0).'&check_out='.$this->day(2).'&adults=2')
            ->assertOk()
            ->assertJsonPath('data.0.room.number', '103')
            ->assertJsonPath('data.0.nightly.1.rate_ugx', 162000)
            ->assertJsonPath('data.0.total_ugx', 342000);

        $this->getJson('/api/portal/availability?check_in='.$this->day(0).'&check_out='.$this->day(2).'&adults=3')
            ->assertOk()->assertJsonCount(0, 'data'); // room sleeps two
    }

    public function test_a_switched_off_discount_is_kept_but_never_applied(): void
    {
        $this->actingAsRole();
        $id = $this->postJson('/api/portal/seasonal-discounts', ['name' => 'Promo', 'starts_on' => $this->day(1), 'ends_on' => $this->day(1), 'percent' => 10])
            ->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');
        $promo = ['name' => 'Promo', 'starts_on' => $this->day(1), 'ends_on' => $this->day(1), 'percent' => 10];

        $this->putJson("/api/portal/seasonal-discounts/{$id}", [...$promo, 'is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/portal/availability?check_in='.$this->day(0).'&check_out='.$this->day(2).'&adults=2')
            ->assertJsonPath('data.0.total_ugx', 360000); // full price both nights
        $this->putJson("/api/portal/seasonal-discounts/{$id}", [...$promo, 'percent' => 20])->assertOk()->assertJsonPath('data.is_active', false); // editing keeps it off

        // While off it blocks nothing; switching back on is checked like a new one.
        $this->postJson('/api/portal/seasonal-discounts', [...$promo, 'name' => 'Flash sale', 'percent' => 5])->assertCreated();
        $this->putJson("/api/portal/seasonal-discounts/{$id}", [...$promo, 'is_active' => true])->assertUnprocessable()->assertJsonValidationErrors('starts_on');
        $this->getJson('/api/portal/availability?check_in='.$this->day(0).'&check_out='.$this->day(2).'&adults=2')
            ->assertJsonPath('data.0.nightly.1.rate_ugx', 171000); // the active 5% only
    }

    public function test_walk_in_booking_with_payment_through_check_out(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);

        $res = $this->book(['payment' => ['method' => 'cash', 'amount_ugx' => 200000]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.reference', 'ARS-10001')
            ->assertJsonPath('data.guest.phone', '+256772000111')
            ->assertJsonPath('data.totals.charges_ugx', 360000)
            ->assertJsonPath('data.totals.balance_ugx', 160000);
        $id = $res->json('data.id');

        // The same room can't be sold twice for those nights.
        $this->book(['check_in' => $this->day(1), 'check_out' => $this->day(3)])->assertStatus(409)->assertJsonValidationErrors('room_id');
        $this->getJson('/api/portal/availability?check_in='.$this->day(1).'&check_out='.$this->day(3))->assertJsonCount(0, 'data');

        // Extras go on the bill; the room charge can't be removed by hand.
        $this->postJson("/api/portal/reservations/{$id}/charges", ['category' => 'restaurant', 'description' => 'Dinner', 'amount_ugx' => 45000])
            ->assertCreated()->assertJsonPath('data.totals.balance_ugx', 205000);
        $roomChargeId = $this->getJson("/api/portal/reservations/{$id}")->json('data.charges.0.id');
        $this->deleteJson("/api/portal/reservations/{$id}/charges/{$roomChargeId}")->assertUnprocessable();

        $this->postJson("/api/portal/reservations/{$id}/check-in")->assertOk()->assertJsonPath('data.status', 'checked_in');
        $this->assertSame('occupied', $this->room->fresh()->status->value);

        // Can't leave owing money, and can't overpay.
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/check-out")->assertUnprocessable();
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/payments", ['method' => 'mtn_momo', 'amount_ugx' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors('amount_ugx');
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/payments", ['method' => 'card', 'amount_ugx' => 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('method'); // online card comes from the gateway
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/payments", ['method' => 'mtn_momo', 'amount_ugx' => 205000, 'reference' => 'MP123'])
            ->assertCreated()->assertJsonPath('data.totals.balance_ugx', 0);

        $this->idempotent()->postJson("/api/portal/reservations/{$id}/check-out")->assertOk()->assertJsonPath('data.status', 'checked_out');
        $this->assertSame('needs_cleaning', $this->room->fresh()->status->value);

        $activity = collect($this->getJson("/api/portal/reservations/{$id}")->json('data.activity'))->pluck('action');
        $this->assertEquals(['reservation.created', 'payment.recorded', 'folio.charge_added', 'reservation.checked_in', 'payment.recorded', 'reservation.checked_out'], $activity->all());
    }

    public function test_check_in_needs_a_clean_room_and_the_arrival_day(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $future = $this->book(['check_in' => $this->day(3), 'check_out' => $this->day(4)])->json('data.id');
        $this->postJson("/api/portal/reservations/{$future}/check-in")->assertUnprocessable();

        $today = $this->book()->json('data.id');
        $this->room->update(['status' => 'needs_cleaning']);
        $this->postJson("/api/portal/reservations/{$today}/check-in")->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'waiting to be cleaned'));
    }

    public function test_cancel_frees_the_room_and_edits_reprice(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->book()->json('data.id');

        $this->patchJson("/api/portal/reservations/{$id}", ['check_out' => $this->day(3)])
            ->assertOk()->assertJsonPath('data.nights', 3)->assertJsonPath('data.totals.charges_ugx', 540000);

        $this->postJson("/api/portal/reservations/{$id}/cancel", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/portal/reservations/{$id}/cancel", ['reason' => 'Guest changed plans'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/portal/reservations/{$id}/check-in")->assertUnprocessable();

        $this->book()->assertCreated(); // the nights are free again
    }

    public function test_past_dates_permissions_and_idempotency(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $this->book(['check_in' => $this->day(-1)])->assertUnprocessable()->assertJsonValidationErrors('check_in');
        $this->withoutHeader('Idempotency-Key')->postJson('/api/portal/reservations', [])->assertStatus(400); // no Idempotency-Key

        $key = (string) Str::uuid();
        $body = ['check_in' => $this->day(0), 'check_out' => $this->day(1), 'adults' => 1, 'room_id' => $this->room->id, 'guest' => ['full_name' => 'A B']];
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/portal/reservations', $body)->assertCreated()->json('data.id');
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/portal/reservations', $body)
            ->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $first);

        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson('/api/portal/reservations')->assertForbidden();
        $this->actingAsRole(UserRole::Owner);
        $this->getJson('/api/portal/reservations')->assertOk()->assertJsonPath('meta.total', 1);
        $this->book()->assertForbidden();
    }

    public function test_list_search_filters_and_calendar(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $this->book();
        $this->book(['check_in' => $this->day(5), 'check_out' => $this->day(6), 'guest' => ['full_name' => 'Amina Nakato']]);

        $this->getJson('/api/portal/reservations?q=nakato')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.guest.full_name', 'Amina Nakato');
        $this->getJson('/api/portal/reservations?q=ARS-10001')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/portal/reservations?from='.$this->day(4))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/portal/reservations?filter[status]=checked_in')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/portal/reservations?sort=-check_in')->assertJsonPath('data.0.guest.full_name', 'Amina Nakato');

        $this->getJson('/api/portal/calendar?from='.$this->day(0).'&days=7')
            ->assertOk()->assertJsonCount(1, 'data.rooms')->assertJsonCount(2, 'data.bookings')
            ->assertJsonPath('data.bookings.0.balance_ugx', 360000);
        $this->getJson('/api/portal/calendar?from='.$this->day(0).'&days=9')->assertUnprocessable();
    }

    public function test_refunds_are_manager_only_and_capped(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->book(['payment' => ['method' => 'cash', 'amount_ugx' => 360000]])->json('data.id');
        $paymentId = $this->getJson("/api/portal/reservations/{$id}")->json('data.payments.0.id');
        $this->idempotent()->postJson("/api/portal/payments/{$paymentId}/refund", ['amount_ugx' => 1000, 'reason' => 'x'])->assertForbidden();

        $this->actingAsRole(UserRole::Manager);
        $this->idempotent()->postJson("/api/portal/payments/{$paymentId}/refund", ['amount_ugx' => 400000, 'reason' => 'Too much'])->assertUnprocessable();
        $this->idempotent()->postJson("/api/portal/payments/{$paymentId}/refund", ['amount_ugx' => 60000, 'reason' => 'Late check-in discount'])
            ->assertCreated()->assertJsonPath('data.kind', 'refund');
        $this->getJson("/api/portal/reservations/{$id}")->assertJsonPath('data.totals.balance_ugx', 60000);

        $this->getJson('/api/portal/payments?filter[kind]=refund')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/portal/payments/summary')->assertOk()->assertJsonPath('data.received.total', 360000);
        $csv = $this->get('/api/portal/payments/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('Moses Ssali', $csv);
        $this->assertStringContainsString('-60000', $csv);
    }

    public function test_receipt_is_emailed(): void
    {
        Mail::fake();
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->book()->json('data.id');

        $this->postJson("/api/portal/reservations/{$id}/receipt")->assertUnprocessable(); // no email on file
        $this->postJson("/api/portal/reservations/{$id}/receipt", ['email' => 'moses@example.com'])->assertOk();
        Mail::assertSent(ReceiptMail::class, fn ($m) => $m->hasTo('moses@example.com'));
    }

    public function test_paying_a_website_hold_at_the_desk_confirms_it(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->book()->json('data.id');
        Reservation::whereKey($id)->update(['status' => 'pending_payment', 'hold_expires_at' => now()->addMinutes(10)]);

        $this->idempotent()->postJson("/api/portal/reservations/{$id}/payments", ['method' => 'cash', 'amount_ugx' => 100000])
            ->assertCreated()->assertJsonPath('data.status', 'pending_payment'); // part-paid: still a hold
        $this->idempotent()->postJson("/api/portal/reservations/{$id}/payments", ['method' => 'cash', 'amount_ugx' => 260000])
            ->assertCreated()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.hold_expires_at', null);
        $this->postJson("/api/portal/reservations/{$id}/check-in")->assertOk();
    }
}
