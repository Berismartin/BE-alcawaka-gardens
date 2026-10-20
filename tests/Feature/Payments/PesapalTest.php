<?php

namespace Tests\Feature\Payments;

use App\Domains\Payments\Gateway\Pesapal\PesapalGateway;
use App\Domains\Reservations\Reservation\Services\ReservationService;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\Payments\Payment;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Online payments through Pesapal API 3.0, with Pesapal itself faked. */
class PesapalTest extends TestCase
{
    use RefreshDatabase;

    private RoomType $standard;

    /** What GetTransactionStatus answers next, per order. */
    private array $status = [];

    private bool $pesapalDown = false;

    private int $orders = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'payments.gateway' => 'pesapal',
            'payments.simulator' => false,
            'payments.pesapal.consumer_key' => 'test-key',
            'payments.pesapal.consumer_secret' => 'test-secret',
            'payments.pesapal.ipn_id' => 'ipn-123',
            'app.frontend_url' => 'https://alcawaka.test',
        ]);
        $this->travelTo(now('Africa/Kampala')->setTime(9, 0));
        $this->standard = RoomType::factory()->create(['name' => 'Standard Room', 'base_rate_ugx' => 180000, 'max_guests' => 2]);
        Room::factory()->create(['number' => '101', 'room_type_id' => $this->standard->id]);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($this->pesapalDown) {
                return Http::response(['error' => ['type' => 'api_error', 'code' => 'unavailable', 'message' => 'Service unavailable'], 'status' => '500'], 503);
            }

            return match (true) {
                str_ends_with($path, 'Auth/RequestToken') => Http::response(['token' => 'tok', 'expiryDate' => now()->addMinutes(5)->toIso8601String(), 'error' => null, 'status' => '200']),
                str_ends_with($path, 'SubmitOrderRequest') => Http::response(['order_tracking_id' => 'trk-'.++$this->orders, 'merchant_reference' => $request['id'], 'redirect_url' => 'https://cybqa.pesapal.com/pesapaliframe/trk-'.$this->orders, 'error' => null, 'status' => '200']),
                str_ends_with($path, 'GetTransactionStatus') => Http::response($this->status[$request['orderTrackingId']] ?? ['status_code' => 0, 'payment_status_description' => 'INVALID', 'currency' => 'UGX', 'error' => ['error_type' => 'api_error', 'code' => 'payment_details_not_found', 'message' => 'Pending Payment'], 'status' => '500']),
                str_ends_with($path, 'RefundRequest') => Http::response(['status' => '200', 'message' => 'Refund request successfully']),
                str_ends_with($path, 'CancelOrder') => Http::response(['status' => '200', 'message' => 'Order successfully cancelled.']),
                default => Http::response([], 404),
            };
        });
    }

    private function book(array $overrides = []): TestResponse
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/booking', array_merge([
            'room_type_id' => $this->standard->id,
            'check_in' => now('Africa/Kampala')->addDays(2)->toDateString(),
            'check_out' => now('Africa/Kampala')->addDays(4)->toDateString(),
            'adults' => 2,
            'guest' => ['full_name' => 'Amina Nakato', 'phone' => '0772 123 456', 'email' => 'amina@example.com'],
            'payment_method' => 'mtn_momo',
        ], $overrides));
    }

    private function paid(string $trackingId, int $amount, string $method = 'MTN', int $code = 1): void
    {
        $this->status[$trackingId] = ['status_code' => $code, 'payment_status_description' => $code === 1 ? 'Completed' : 'Failed', 'amount' => $amount, 'currency' => 'UGX',
            'confirmation_code' => 'CONF-'.$trackingId, 'payment_method' => $method, 'payment_account' => '2567XXXX456', 'merchant_reference' => 'x', 'status' => '200'];
    }

    private function ipn(string $trackingId): TestResponse
    {
        return $this->getJson("/api/payments/pesapal/ipn?OrderTrackingId={$trackingId}&OrderNotificationType=IPNCHANGE&OrderMerchantReference=ref");
    }

    public function test_guest_pays_on_pesapal_and_the_ipn_confirms_the_booking_once(): void
    {
        $res = $this->book()->assertCreated()
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.payment.gateway', 'pesapal')
            ->assertJsonPath('data.payment.redirect_url', 'https://cybqa.pesapal.com/pesapaliframe/trk-1');
        $payment = Payment::firstOrFail();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'SubmitOrderRequest')
            && $r['id'] === $payment->id && $r['amount'] === 360000 && $r['currency'] === 'UGX' && $r['notification_id'] === 'ipn-123'
            && $r['callback_url'] === 'https://alcawaka.test/payment/return' && $r['billing_address']['email_address'] === 'amina@example.com'
            && $r->hasHeader('Authorization', 'Bearer tok'));

        // Not paid yet: Pesapal says INVALID; we keep waiting, and reply in Pesapal's format.
        $this->ipn('trk-1')->assertOk()->assertExactJson(['orderNotificationType' => 'IPNCHANGE', 'orderTrackingId' => 'trk-1', 'orderMerchantReference' => 'ref', 'status' => 200]);
        $this->assertSame('pending', $payment->refresh()->status->value);

        $this->paid('trk-1', 360000, 'Airtel');
        $this->ipn('trk-1')->assertOk()->assertJsonPath('status', 200);
        $this->ipn('trk-1')->assertOk(); // Pesapal retries: nothing is counted twice
        $payment->refresh();
        $this->assertSame(['succeeded', 'airtel_money', 'CONF-trk-1'], [$payment->status->value, $payment->method->value, $payment->gateway_confirmation_code]);
        $reservation = Reservation::firstOrFail();
        $this->assertSame('confirmed', $reservation->status->value);
        $this->assertSame(0, $reservation->load(['charges', 'payments'])->balance());

        // The guest's browser comes back from Pesapal: told where to go, key included.
        $this->postJson('/api/payments/return', ['order_tracking_id' => 'trk-1'])->assertOk()
            ->assertJsonPath('data.type', 'room')->assertJsonPath('data.reference', $res->json('data.reference'))
            ->assertJsonPath('data.key', $res->json('data.key'))->assertJsonPath('data.status', 'succeeded');
        $this->postJson('/api/payments/return', ['order_tracking_id' => 'nope'])->assertNotFound();
        $this->ipn('unknown-order')->assertOk()->assertJsonPath('status', 200); // acknowledged so Pesapal stops retrying
        $this->getJson('/api/payments/pesapal/ipn?OrderTrackingId=bad%20id')->assertUnprocessable();
    }

    public function test_declined_retry_and_gateway_outage_never_charge_twice(): void
    {
        $res = $this->book()->assertCreated();
        $ref = $res->json('data.reference');
        $key = $res->json('data.key');

        $this->paid('trk-1', 360000, 'MTN', code: 2);
        $this->postJson('/api/payments/return', ['order_tracking_id' => 'trk-1'])->assertOk()->assertJsonPath('data.status', 'failed');

        // Pesapal is down when the guest retries: nothing charged, the room stays held, a clear message.
        $this->pesapalDown = true;
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'card'])->assertOk()
            ->assertJsonPath('data.status', 'pending_payment')->assertJsonPath('data.payment.status', 'failed');
        $this->assertStringContainsString('Pesapal', Payment::latest('created_at')->latest('id')->first()->notes);

        $this->pesapalDown = false;
        $this->postJson("/api/booking/{$ref}/pay", ['key' => $key, 'payment_method' => 'card'])->assertOk()
            ->assertJsonPath('data.payment.redirect_url', 'https://cybqa.pesapal.com/pesapaliframe/trk-2');
        $this->paid('trk-2', 360000, 'Visa');
        $this->travel(2)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful(); // no IPN reached us: the job settles it
        $this->assertSame(1, Payment::where('status', 'succeeded')->count());
        $this->assertSame('confirmed', Reservation::firstOrFail()->status->value);
        $this->assertSame('card', Payment::where('status', 'succeeded')->first()->method->value);
    }

    public function test_paying_after_the_hold_lapsed_keeps_the_money_and_tells_staff(): void
    {
        $manager = User::factory()->create();
        $manager->forceFill(['role' => UserRole::Manager])->save();
        $this->book()->assertCreated();

        $this->travel(21)->minutes();
        $this->artisan('payments:reconcile')->assertSuccessful(); // still unpaid at Pesapal
        app(ReservationService::class)->expireHolds();
        $this->assertSame('expired', Reservation::firstOrFail()->status->value);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'CancelOrder') && $r['order_tracking_id'] === 'trk-1');

        // The guest still finishes on Pesapal's page.
        $this->paid('trk-1', 360000);
        $this->ipn('trk-1')->assertOk();
        $this->assertSame('succeeded', Payment::firstOrFail()->status->value);
        $this->assertSame('expired', Reservation::firstOrFail()->status->value); // the room may be gone: staff decide
        $this->assertStringContainsString('had already', $manager->notifications()->first()->data['title']);
    }

    public function test_refunds_follow_pesapal_rules_and_complete_when_reversed(): void
    {
        $this->book()->assertCreated();
        $this->paid('trk-1', 360000);
        $this->ipn('trk-1');
        $payment = Payment::firstOrFail();
        $this->actingAsRole(UserRole::Manager);

        // Staff can't hand-approve or hand-fail what Pesapal confirms.
        $this->patchJson("/api/portal/payments/{$payment->id}/settle", ['status' => 'failed'])->assertUnprocessable();
        $this->getJson('/api/portal/payments')->assertJsonPath('data.0.gateway_details.confirmation_code', 'CONF-trk-1')
            ->assertJsonPath('data.0.can_settle', false)->assertJsonPath('data.0.refund_rule', 'Pesapal: one full refund only (mobile money)');

        $this->idempotent()->postJson("/api/portal/payments/{$payment->id}/refund", ['amount_ugx' => 100000, 'reason' => 'Changed plans'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount_ugx'); // mobile money: full only
        $refund = $this->idempotent()->postJson("/api/portal/payments/{$payment->id}/refund", ['amount_ugx' => 360000, 'reason' => 'Changed plans'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'RefundRequest') && $r['confirmation_code'] === 'CONF-trk-1' && $r['amount'] === 360000);
        $this->idempotent()->postJson("/api/portal/payments/{$payment->id}/refund", ['amount_ugx' => 360000, 'reason' => 'Again'])
            ->assertUnprocessable(); // Pesapal: one refund per payment
        $this->assertSame(0, Reservation::firstOrFail()->load(['charges', 'payments'])->balance()); // not out until Pesapal pays it

        $this->status['trk-1']['status_code'] = 3; // Pesapal paid the refund: REVERSED
        $this->postJson("/api/portal/payments/{$refund}/check")->assertOk()->assertJsonPath('data.status', 'succeeded');
        $this->assertSame(360000, Reservation::firstOrFail()->load(['charges', 'payments'])->balance());
    }

    public function test_facility_day_pass_paid_through_pesapal(): void
    {
        $pool = Facility::factory()->create(['name' => 'Swimming pool', 'access' => 'paid', 'price_ugx' => 20000, 'capacity' => 30]);
        $res = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/facility-bookings', [
            'facility_id' => $pool->id, 'date' => now('Africa/Kampala')->addDays(3)->toDateString(), 'time' => '10:00', 'party_size' => 2,
            'guest' => ['full_name' => 'Grace Achieng', 'phone' => '0782 555 010', 'email' => 'grace@example.com'], 'payment_method' => 'card',
        ])->assertCreated()->assertJsonPath('data.payment.redirect_url', 'https://cybqa.pesapal.com/pesapaliframe/trk-1');

        $this->paid('trk-1', 40000, 'Mastercard');
        $this->postJson('/api/payments/return', ['order_tracking_id' => 'trk-1'])->assertOk()
            ->assertJsonPath('data.type', 'facility')->assertJsonPath('data.id', $res->json('data.id'))->assertJsonPath('data.key', $res->json('data.key'));
        $this->getJson("/api/facility-bookings/{$res->json('data.id')}?key={$res->json('data.key')}")->assertJsonPath('data.status', 'confirmed');
    }

    public function test_a_different_currency_is_never_counted(): void
    {
        $this->book()->assertCreated();
        $this->status['trk-1'] = ['status_code' => 1, 'amount' => 100, 'currency' => 'USD', 'confirmation_code' => 'C', 'payment_method' => 'Visa', 'status' => '200'];
        $this->ipn('trk-1')->assertOk();
        $this->assertSame('pending', Payment::firstOrFail()->status->value);
        $this->assertSame('pending_payment', Reservation::firstOrFail()->status->value);
    }

    public function test_token_is_reused_and_renewed_once_after_a_401(): void
    {
        $tokens = 0;
        $statusCalls = 0;
        Http::swap(new Factory); // drop setUp's Pesapal fake: this test answers by itself
        Http::fake(function (Request $request) use (&$tokens, &$statusCalls) {
            if (str_ends_with($request->url(), 'RequestToken')) {
                return Http::response(['token' => 'tok-'.++$tokens, 'status' => '200', 'error' => null]);
            }
            $statusCalls++;

            // The first token is "revoked" after one use.
            return $request->hasHeader('Authorization', 'Bearer tok-1') && $statusCalls > 1
                ? Http::response(['error' => ['code' => 'invalid_token']], 401)
                : Http::response(['status_code' => 0, 'status' => '200']);
        });
        $gateway = app(PesapalGateway::class);
        $gateway->fetchStatus('a');
        $gateway->fetchStatus('b'); // 401 on tok-1: one new token, then success
        $gateway->fetchStatus('c'); // reuses tok-2
        $this->assertSame(2, $tokens);
        $this->assertSame(4, $statusCalls);
    }
}
