<?php

namespace Tests\Feature\Payments;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\PaymentLinkMail;
use App\Models\Facilities\Facility;
use App\Models\Payments\Payment;
use App\Models\Payments\PaymentLink;
use App\Models\Rooms\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Pesapal payments started by staff (desk and POS) and emailed invoice links, with Pesapal faked. */
class StaffOnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private array $status = [];

    private int $orders = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'payments.gateway' => 'pesapal', 'payments.simulator' => false,
            'payments.pesapal.consumer_key' => 'k', 'payments.pesapal.consumer_secret' => 's', 'payments.pesapal.ipn_id' => 'ipn-1',
            'app.frontend_url' => 'https://alcawaka.test',
        ]);
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, 'RequestToken') => Http::response(['token' => 'tok', 'status' => '200', 'error' => null]),
                str_ends_with($path, 'SubmitOrderRequest') => Http::response(['order_tracking_id' => 'trk-'.++$this->orders, 'redirect_url' => 'https://pay.pesapal.test/trk-'.$this->orders, 'status' => '200', 'error' => null]),
                str_ends_with($path, 'GetTransactionStatus') => Http::response($this->status[$request['orderTrackingId']]
                    ?? ['status_code' => 0, 'currency' => 'UGX', 'error' => ['code' => 'payment_details_not_found', 'message' => 'Pending Payment'], 'status' => '500']),
                default => Http::response(['status' => '200']),
            };
        });
    }

    private function paid(string $trackingId, int $amount, string $method = 'MTN'): void
    {
        $this->status[$trackingId] = ['status_code' => 1, 'amount' => $amount, 'currency' => 'UGX', 'confirmation_code' => "C-{$trackingId}", 'payment_method' => $method, 'status' => '200'];
    }

    private function reservation(): string
    {
        $room = Room::factory()->create(); // the factory's room type: 180,000 a night

        return $this->idempotent()->postJson('/api/portal/reservations', [
            'check_in' => now('Africa/Kampala')->toDateString(), 'check_out' => now('Africa/Kampala')->addDays(2)->toDateString(),
            'adults' => 1, 'room_id' => $room->id, 'guest' => ['full_name' => 'Joseph Mugisha', 'phone' => '0772 111 222', 'email' => 'joseph@example.com'],
        ])->assertCreated()->json('data.id');
    }

    private function balance(string $id): int
    {
        return $this->getJson("/api/portal/reservations/{$id}")->json('data.totals.balance_ugx');
    }

    public function test_front_desk_takes_a_pesapal_deposit_and_it_is_logged(): void
    {
        $desk = $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->reservation();
        $due = $this->balance($id);
        $pay = fn (array $body): TestResponse => $this->idempotent()->postJson("/api/portal/reservations/{$id}/online-payments", $body);

        $pay(['amount_ugx' => $due + 1, 'method' => 'mtn_momo'])->assertUnprocessable()->assertJsonValidationErrors('amount_ugx');
        $pay(['amount_ugx' => 1000, 'method' => 'cash'])->assertUnprocessable()->assertJsonValidationErrors('method'); // cash is recorded at the desk, not via Pesapal
        $paymentId = $pay(['amount_ugx' => 100000, 'method' => 'mtn_momo'])->assertCreated()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.redirect_url', 'https://pay.pesapal.test/trk-1')
            ->assertJsonPath('data.can_check', true)->assertJsonPath('data.can_settle', false)->json('data.id');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'SubmitOrderRequest') && $r['amount'] === 100000
            && $r['callback_url'] === 'https://alcawaka.test/payment/done' && $r['billing_address']['email_address'] === 'joseph@example.com');

        $this->paid('trk-1', 100000, 'Airtel');
        $this->postJson("/api/portal/payments/{$paymentId}/check")->assertOk()->assertJsonPath('data.status', 'succeeded')->assertJsonPath('data.method', 'airtel_money');
        $this->assertSame($due - 100000, $this->balance($id));
        // Logged: who asked for it, and the money arriving.
        $this->getJson("/api/portal/reservations/{$id}")->assertJsonFragment(['action' => 'payment.requested'])->assertJsonFragment(['action' => 'payment.received']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.requested', 'user_id' => $desk->id]);

        $this->actingAsRole(UserRole::Housekeeping);
        $pay(['amount_ugx' => 1000, 'method' => 'mtn_momo'])->assertForbidden();
    }

    public function test_pos_facility_pay_later_booking_settled_through_pesapal(): void
    {
        $pool = Facility::factory()->create(['name' => 'Pool', 'access' => 'paid', 'price_ugx' => 20000, 'capacity' => 30]);
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => now('Africa/Kampala')->addHour()->format('Y-m-d H:i'), 'party_size' => 3,
            'charge_mode' => 'pay_later', 'guest_name' => 'Grace Achieng', 'amount_ugx' => 60000,
        ])->assertCreated()->assertJsonPath('data.status', 'pending_payment')->json('data.id');

        $paymentId = $this->idempotent()->postJson("/api/portal/facility-bookings/{$id}/online-payments", ['amount_ugx' => 60000, 'method' => 'card'])
            ->assertCreated()->json('data.id');
        $this->paid('trk-1', 60000, 'Visa');
        $this->getJson('/api/payments/pesapal/ipn?OrderTrackingId=trk-1')->assertOk();

        $this->assertSame('succeeded', Payment::find($paymentId)->status->value);
        $this->getJson("/api/portal/facility-bookings/{$id}")->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.charge_mode', 'paid');
    }

    public function test_invoice_email_is_remembered_when_the_booking_had_none(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $pool = Facility::factory()->create(['name' => 'Pool', 'access' => 'paid', 'price_ugx' => 20000, 'capacity' => 30]);
        $id = $this->idempotent()->postJson('/api/portal/facility-bookings', [
            'facility_id' => $pool->id, 'starts_at' => now('Africa/Kampala')->addHour()->format('Y-m-d H:i'), 'party_size' => 2,
            'charge_mode' => 'pay_later', 'guest_name' => 'Grace Achieng', 'amount_ugx' => 40000,
        ])->assertCreated()->assertJsonPath('data.guest_email', null)->json('data.id');

        $this->postJson("/api/portal/facility-bookings/{$id}/payment-links", ['email' => 'grace@example.com'])->assertCreated();
        $this->getJson("/api/portal/facility-bookings/{$id}")->assertJsonPath('data.guest_email', 'grace@example.com');
    }

    public function test_emailed_invoice_link_is_paid_by_the_guest_once(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->reservation();
        $due = $this->balance($id);

        $this->postJson("/api/portal/reservations/{$id}/payment-links", ['email' => 'joseph@example.com', 'amount_ugx' => $due + 5])
            ->assertUnprocessable()->assertJsonValidationErrors('amount_ugx');
        $url = $this->postJson("/api/portal/reservations/{$id}/payment-links", ['email' => 'joseph@example.com', 'message' => 'Thank you for staying with us.'])
            ->assertCreated()->assertJsonPath('data.emailed', true)->assertJsonPath('data.amount_ugx', $due)->json('data.url');
        $token = basename($url);
        $this->assertStringStartsWith('https://alcawaka.test/pay/', $url);
        Mail::assertSent(PaymentLinkMail::class, fn (PaymentLinkMail $m) => $m->hasTo('joseph@example.com') && count($m->attachments()) === 1); // with the invoice PDF

        // The guest, signed out.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/pay/{$token}")->assertOk()->assertJsonPath('data.status', 'open')->assertJsonPath('data.due_ugx', $due)
            ->assertJsonPath('data.guest_name', 'Joseph Mugisha')->assertJsonPath('data.message', 'Thank you for staying with us.')->assertJsonMissingPath('data.token');
        $this->postJson("/api/pay/{$token}/checkout", ['method' => 'mtn_momo'])->assertOk()->assertJsonPath('data.redirect_url', 'https://pay.pesapal.test/trk-1');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'SubmitOrderRequest') && $r['callback_url'] === "https://alcawaka.test/pay/{$token}" && $r['amount'] === $due);

        // Back from Pesapal: opening the page settles the payment.
        $this->paid('trk-1', $due);
        $this->getJson("/api/pay/{$token}")->assertJsonPath('data.status', 'paid')->assertJsonPath('data.due_ugx', 0)->assertJsonPath('data.last_payment.status', 'succeeded');
        $this->postJson("/api/pay/{$token}/checkout", ['method' => 'mtn_momo'])->assertUnprocessable(); // already paid
        $this->assertSame(1, Payment::where('status', 'succeeded')->count());

        $this->getJson('/api/pay/'.str_repeat('x', 48))->assertNotFound();
    }

    public function test_a_link_for_part_of_the_bill_and_an_expired_link(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);
        $id = $this->reservation();
        $url = $this->postJson("/api/portal/reservations/{$id}/payment-links", ['email' => 'joseph@example.com', 'amount_ugx' => 50000])
            ->assertCreated()->assertJsonPath('data.amount_ugx', 50000)->json('data.url');
        $token = basename($url);
        $this->getJson("/api/pay/{$token}")->assertJsonPath('data.pay_now_ugx', 50000);

        PaymentLink::query()->update(['expires_at' => now()->subMinute()]);
        $this->getJson("/api/pay/{$token}")->assertJsonPath('data.status', 'expired');
        $this->postJson("/api/pay/{$token}/checkout", ['method' => 'card'])->assertUnprocessable();
        $this->assertSame(0, Payment::count());
    }
}
