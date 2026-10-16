<?php

namespace Tests\Feature\Pos;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private function terminalKey(): string
    {
        $this->actingAsRole(UserRole::Manager);
        $key = $this->postJson('/api/portal/pos/terminals', ['name' => 'Front desk tablet'])->assertCreated()->json('data.key');
        $this->app['auth']->forgetGuards();

        return $key;
    }

    private function staffWithPin(string $pin = '2580', UserRole $role = UserRole::FrontDesk): User
    {
        $user = User::factory()->create(['name' => 'Ruth Namusoke']);
        $user->forceFill(['role' => $role, 'pin_hash' => bcrypt($pin)])->save();

        return $user;
    }

    public function test_terminal_is_required_and_lists_only_staff_with_a_pin(): void
    {
        $key = $this->terminalKey();
        $ruth = $this->staffWithPin();
        User::factory()->create(['name' => 'No Pin'])->forceFill(['role' => UserRole::FrontDesk])->save();

        $this->getJson('/api/pos/staff')->assertForbidden();
        $this->getJson('/api/pos/staff', ['X-Terminal-Key' => 'wrong'])->assertForbidden();
        $this->getJson('/api/pos/staff', ['X-Terminal-Key' => $key])->assertOk()
            ->assertJsonPath('data.terminal.name', 'Front desk tablet')
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.id', $ruth->id)
            ->assertJsonMissingPath('data.staff.0.email');
    }

    public function test_pin_sign_in_locks_after_five_wrong_tries(): void
    {
        $key = $this->terminalKey();
        $ruth = $this->staffWithPin();
        $h = ['X-Terminal-Key' => $key];

        foreach (range(1, 4) as $i) {
            $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '0000'], $h)->assertUnprocessable();
        }
        $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '0000'], $h)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'Locked'));
        // Even the right PIN is refused while locked.
        $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '2580'], $h)->assertUnprocessable();

        $this->travel(6)->minutes();
        $token = $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '2580'], $h)->assertOk()->assertJsonPath('data.user.role', 'front_desk')->json('data.token');
        $this->assertNotNull($ruth->tokens()->first()->expires_at);

        // The token is a normal staff token, with Ruth's own permissions.
        $this->withToken($token)->getJson('/api/portal/dashboard')->assertOk();
        $this->withToken($token)->getJson('/api/portal/staff')->assertForbidden();
    }

    public function test_pin_must_not_work_without_a_terminal_and_revoking_signs_out(): void
    {
        $key = $this->terminalKey();
        $ruth = $this->staffWithPin();
        $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '2580'])->assertForbidden();
        $token = $this->postJson('/api/pos/login', ['user_id' => $ruth->id, 'pin' => '2580'], ['X-Terminal-Key' => $key])->json('data.token');

        $this->actingAsRole(UserRole::Manager);
        $id = $this->getJson('/api/portal/pos/terminals')->json('data.0.id');
        $this->deleteJson("/api/portal/pos/terminals/{$id}")->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/pos/staff', ['X-Terminal-Key' => $key])->assertForbidden();
    }

    public function test_staff_set_their_own_pin_and_managers_can_clear_it(): void
    {
        $ruth = User::factory()->create();
        $ruth->forceFill(['role' => UserRole::FrontDesk])->save();
        Sanctum::actingAs($ruth);

        $this->patchJson('/api/auth/me/pin', ['pin' => '12a4', 'pin_confirmation' => '12a4', 'current_password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('pin');
        $this->patchJson('/api/auth/me/pin', ['pin' => '1111', 'pin_confirmation' => '1111', 'current_password' => 'password'])->assertUnprocessable();
        $this->patchJson('/api/auth/me/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'current_password' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/auth/me/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'current_password' => 'password'])->assertOk();
        $this->getJson('/api/auth/me')->assertJsonPath('data.has_pin', true);
        $this->deleteJson("/api/portal/pos/staff/{$ruth->id}/pin")->assertForbidden();

        $this->actingAsRole(UserRole::Manager);
        $this->deleteJson("/api/portal/pos/staff/{$ruth->id}/pin")->assertOk();
        $this->assertNull($ruth->fresh()->pin_hash);
    }
}
