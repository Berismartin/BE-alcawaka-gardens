<?php

namespace Tests\Feature\Booking;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\BookingConfirmedMail;
use App\Models\Content\Enquiry;
use App\Models\Guests\Guest;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OnlineBookingTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $standard;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(now('Africa/Kampala')->setTime(9, 0));
        $this->standard = RoomType::factory()->create(['name' => 'Standard Room', 'base_rate_ugx' => 180000, 'max_guests' => 2]);
        Room::factory()->create(['number' => '101', 'room_type_id' => $this->standard->id]);
        RoomType::factory()->create(['name' => 'Suite', 'base_rate_ugx' => 380000, 'max_guests' => 4]); // no rooms: sold out
    }

    private function day(int $n): string
    {
        return now('Africa/Kampala')->addDays($n)->toDateString();
    }

    private function book(array $overrides = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/booking', array_merge([
            'room_type_id' => $this->standard->id,
            'check_in' => $this->day(2),
            'check_out' => $this->day(4),
            'adults' => 2,
            'guest' => ['full_name' => 'Amina Nakato', 'phone' => '0772 123 456', 'email' => 'amina@example.com'],
            'arrival_time' => '15:00',
            'payment_method' => 'mtn_momo',
        ], $overrides));
    }

    public function test_search_shows_left_and_sold_out_types(): void
    {
        $this->getJson("/api/booking/search?check_in={$this->day(2)}&check_out={$this->day(4)}&adults=2")
            ->assertOk()
            ->assertJsonPath('data.0.room_type.name', 'Standard Room')
            ->assertJsonPath('data.0.rooms_left', 1)
            ->assertJsonPath('data.0.total_ugx', 360000)
            ->assertJsonPath('data.1.room_type.name', 'Suite')
            ->assertJsonPath('data.1.rooms_left', 0)
            ->assertJsonPath('data.1.total_ugx', null);
        $this->getJson("/api/booking/search?check_in={$this->day(-1)}&check_out={$this->day(1)}")->assertUnprocessable();
    }

    public function test_book_hold_decline_retry_and_approve(): void
    {
        $res = $this->book()->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment.status', 'pending')
            ->assertJsonPath('data.payment.amount_ugx', 360000)
            ->assertJsonPath('data.special_requests', 'Arriving around 15:00')
            ->assertJsonMissingPath('data.room');
        $ref = $res->json('data.reference');
        $key = $res->json('data.key');
        $this->assertNotNull($key);

        // The room is held: nobody else can book it meanwhile.
        $this->book()->assertStatus(409);
        // Nothing without the key.
        $this->getJson("/api/booking/{$ref}?key=wrong")->assertNotFound();
        $this->getJson("/api/booking/{$ref}?key={$key}")->assertOk()->assertJsonMissingPath('data.key');

        $this->postJson("/api/booking/{$ref}/simulate", ['key' => $key, 'outcome' => 'decline'])->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.payment.status', 'failed');
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'airtel_money'])->assertOk()
            ->assertJsonPath('data.payment.method', 'airtel_money')->assertJsonPath('data.payment.status', 'pending');
        $this->postJson("/api/booking/{$ref}/simulate", ['key' => $key, 'outcome' => 'approve'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.totals.balance_ugx', 0)
            ->assertJsonPath('data.paid_with.0', 'Airtel Money');

        Mail::assertSent(BookingConfirmedMail::class, function (BookingConfirmedMail $m) use ($ref) {
            $html = $m->render(); // the fake never renders, so render it here

            return $m->hasTo('amina@example.com') && str_contains($html, $ref) && str_contains($html, 'Standard Room');
        });
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'mtn_momo'])->assertUnprocessable();
    }

    public function test_unpaid_hold_expires_and_frees_the_room(): void
    {
        $res = $this->book();
        $this->travel(11)->minutes();
        $this->getJson("/api/booking/{$res->json('data.reference')}?key={$res->json('data.key')}")
            ->assertOk()->assertJsonPath('data.status', 'expired')->assertJsonPath('data.payment.status', 'failed');
        $this->book()->assertCreated();
    }

    public function test_lookup_and_guest_requests_reach_the_front_desk(): void
    {
        $ref = $this->book()->json('data.reference');

        $this->postJson('/api/booking/lookup', ['reference' => $ref, 'contact' => 'someone@else.com'])->assertUnprocessable();
        $key = $this->postJson('/api/booking/lookup', ['reference' => strtolower($ref), 'contact' => '+256 772 123 456'])
            ->assertOk()->assertJsonPath('data.reference', $ref)->json('data.key');

        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'change'])->assertUnprocessable()->assertJsonValidationErrors('new_check_in');
        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'change', 'new_check_in' => $this->day(10), 'new_check_out' => $this->day(12), 'note' => 'Same room please'])->assertOk();
        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'cancel'])->assertOk();

        $this->assertDatabaseHas('enquiries', ['event_type' => 'Date change request', 'name' => 'Amina Nakato']);
        $this->assertDatabaseHas('enquiries', ['event_type' => 'Cancellation request']);
    }

    public function test_simulator_is_off_in_production_config(): void
    {
        config(['payments.simulator' => false]);
        $res = $this->book();
        $this->postJson("/api/booking/{$res->json('data.reference')}/simulate", ['key' => $res->json('data.key'), 'outcome' => 'approve'])->assertNotFound();
        $this->assertFalse($this->getJson("/api/booking/{$res->json('data.reference')}?key={$res->json('data.key')}")->json('data.simulator'));
    }

    public function test_guest_retries_are_idempotent(): void
    {
        $key = (string) Str::uuid();
        $body = ['room_type_id' => $this->standard->id, 'check_in' => $this->day(2), 'check_out' => $this->day(3), 'adults' => 1, 'guest' => ['full_name' => 'A B', 'phone' => '0772000000', 'email' => 'a@b.co'], 'payment_method' => 'card'];
        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/booking', $body)->assertCreated()->json('data.reference');
        $this->withHeader('Idempotency-Key', $key)->postJson('/api/booking', $body)->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.reference', $first);
    }

    /** @return array{0: string, 1: string} reference and key of a paid booking */
    private function paidBooking(array $overrides = []): array
    {
        $res = $this->book($overrides);
        [$ref, $key] = [$res->json('data.reference'), $res->json('data.key')];
        $this->postJson("/api/booking/{$ref}/simulate", ['key' => $key, 'outcome' => 'approve'])->assertOk();

        return [$ref, $key];
    }

    public function test_receipt_pdf_for_guest_and_staff(): void
    {
        $held = $this->book();
        $this->get("/api/booking/{$held->json('data.reference')}/receipt?key={$held->json('data.key')}")->assertUnprocessable();
        $this->postJson("/api/booking/{$held->json('data.reference')}/simulate", ['key' => $held->json('data.key'), 'outcome' => 'approve']);
        $ref = $held->json('data.reference');

        $this->get("/api/booking/{$ref}/receipt?key=wrong")->assertNotFound();
        $pdf = $this->get("/api/booking/{$ref}/receipt?key={$held->json('data.key')}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString("receipt-{$ref}.pdf", $pdf->headers->get('Content-Disposition'));

        $id = Reservation::where('number', (int) substr($ref, 4))->value('id');
        $this->get("/api/portal/reservations/{$id}/receipt.pdf")->assertUnauthorized();
        $this->actingAsRole(UserRole::FrontDesk);
        $this->assertStringStartsWith('%PDF', $this->get("/api/portal/reservations/{$id}/receipt.pdf")->assertOk()->getContent());
    }

    public function test_staff_apply_a_date_change_moving_room_if_needed_then_a_cancellation(): void
    {
        Room::factory()->create(['number' => '102', 'room_type_id' => $this->standard->id]);
        [$ref, $key] = $this->paidBooking();
        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'change', 'new_check_in' => $this->day(10), 'new_check_out' => $this->day(13)])->assertOk();
        // Someone else takes room 101 for the new dates first.
        $this->paidBooking(['check_in' => $this->day(10), 'check_out' => $this->day(12), 'guest' => ['full_name' => 'Okello John', 'phone' => '0701000000', 'email' => 'o@example.com']]);
        $change = Enquiry::where('request_type', 'change')->firstOrFail();

        $this->postJson("/api/portal/enquiries/{$change->id}/apply")->assertUnauthorized();
        $this->actingAsRole(UserRole::FrontDesk);
        $this->getJson('/api/portal/enquiries')->assertOk()->assertJsonPath('data.0.booking_request.reference', $ref);
        $this->postJson("/api/portal/enquiries/{$change->id}/apply")->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.booking_request.reservation_status', 'confirmed');
        $moved = Reservation::where('number', (int) substr($ref, 4))->firstOrFail();
        $this->assertSame($this->day(10), $moved->check_in->toDateString());
        $this->assertSame('102', $moved->room->number);
        $this->assertSame(540000, $moved->charges->sum('amount_ugx')); // three nights now, so 180,000 is due
        Mail::assertSent(BookingConfirmedMail::class, fn (BookingConfirmedMail $m) => $m->kind === 'changed' && str_contains($m->render(), 'Balance due'));
        $this->postJson("/api/portal/enquiries/{$change->id}/apply")->assertUnprocessable();

        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'cancel'])->assertOk();
        $cancel = Enquiry::where('request_type', 'cancel')->firstOrFail();
        $this->postJson("/api/portal/enquiries/{$cancel->id}/apply")->assertOk()->assertJsonPath('data.booking_request.reservation_status', 'cancelled');
        Mail::assertSent(BookingConfirmedMail::class, fn (BookingConfirmedMail $m) => $m->kind === 'cancelled' && str_contains($m->render(), 'cancelled'));
    }

    public function test_a_change_with_no_free_room_is_refused_and_left_open(): void
    {
        [$ref, $key] = $this->paidBooking();
        $this->postJson("/api/booking/{$ref}/request", ['key' => $key, 'type' => 'change', 'new_check_in' => $this->day(10), 'new_check_out' => $this->day(12)])->assertOk();
        $this->paidBooking(['check_in' => $this->day(11), 'check_out' => $this->day(12), 'guest' => ['full_name' => 'Okello John', 'phone' => '0701000000', 'email' => 'o@example.com']]);
        $this->actingAsRole(UserRole::FrontDesk);
        $id = Enquiry::where('request_type', 'change')->value('id');
        $this->postJson("/api/portal/enquiries/{$id}/apply")->assertStatus(409);
        $this->assertDatabaseHas('enquiries', ['id' => $id, 'status' => 'new']);
    }

    public function test_a_phone_match_alone_never_attaches_a_website_booking_to_someone_else(): void
    {
        $patience = Guest::factory()->create(['full_name' => 'Patience Achieng', 'phone' => '+256772123456', 'email' => 'patience@example.com']);

        // Someone types Patience's phone with their own name and email: a new guest, nothing of hers shown.
        $res = $this->book(['check_out' => $this->day(3)])->assertCreated()->assertJsonPath('data.guest.full_name', 'Amina Nakato');
        $this->assertNotSame($patience->id, Reservation::where('number', (int) substr($res->json('data.reference'), 4))->value('guest_id'));
        $this->assertSame('Patience Achieng', $patience->fresh()->full_name);

        // Patience herself, same phone and email (any case): her existing record.
        $mine = $this->book(['check_in' => $this->day(5), 'check_out' => $this->day(6), 'guest' => ['full_name' => 'Patience A.', 'phone' => '0772 123 456', 'email' => 'Patience@Example.com']])
            ->assertCreated()->json('data.reference');
        $this->assertSame($patience->id, Reservation::where('number', (int) substr($mine, 4))->value('guest_id'));
    }

    public function test_cash_on_arrival_confirms_at_once_and_pesapal_is_gone(): void
    {
        $this->book(['payment_method' => 'pesapal'])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

        $res = $this->book(['payment_method' => 'cash'])->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment', null)
            ->assertJsonPath('data.hold_expires_at', null)
            ->assertJsonPath('data.totals.balance_ugx', 360000);
        Mail::assertSent(BookingConfirmedMail::class, fn (BookingConfirmedMail $m) => str_contains($m->render(), 'at the front desk when you arrive'));

        // No hold: still confirmed long after ten minutes.
        $this->travel(30)->minutes();
        $this->getJson("/api/booking/{$res->json('data.reference')}?key={$res->json('data.key')}")->assertJsonPath('data.status', 'confirmed');
    }

    public function test_a_guest_whose_mobile_money_fails_can_switch_to_cash(): void
    {
        Room::factory()->create(['number' => '102', 'room_type_id' => $this->standard->id]);
        $res = $this->book();
        [$ref, $key] = [$res->json('data.reference'), $res->json('data.key')];
        $this->postJson("/api/booking/{$ref}/simulate", ['key' => $key, 'outcome' => 'decline']);
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'cash'])->assertOk()
            ->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.hold_expires_at', null)->assertJsonPath('data.totals.paid_ugx', 0);
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'mtn_momo'])->assertUnprocessable();
    }
}
