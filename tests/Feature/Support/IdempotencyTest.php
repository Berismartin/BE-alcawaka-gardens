<?php

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private int $runs = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['api', 'auth:sanctum', 'idempotent:required'])->post('/api/_test/pay', function () {
            $this->runs++;

            return response()->json(['success' => true, 'data' => ['run' => $this->runs], 'message' => null], 201);
        });
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_a_retry_with_the_same_key_replays_the_first_response(): void
    {
        $headers = ['Idempotency-Key' => 'pay-123'];

        $this->postJson('/api/_test/pay', ['amount' => 1000], $headers)->assertCreated()->assertJsonPath('data.run', 1);
        $this->postJson('/api/_test/pay', ['amount' => 1000], $headers)
            ->assertCreated()
            ->assertJsonPath('data.run', 1)
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, $this->runs);
    }

    public function test_key_is_required_and_cannot_be_reused_for_a_different_body(): void
    {
        $this->postJson('/api/_test/pay', ['amount' => 1000])->assertStatus(400);

        $this->postJson('/api/_test/pay', ['amount' => 1000], ['Idempotency-Key' => 'k1'])->assertCreated();
        $this->postJson('/api/_test/pay', ['amount' => 9999], ['Idempotency-Key' => 'k1'])->assertUnprocessable();
    }
}
