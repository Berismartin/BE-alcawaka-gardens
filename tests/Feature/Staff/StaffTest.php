<?php

namespace Tests\Feature\Staff;

use App\Domains\Users\User\Enums\UserRole;
use App\Mail\StaffInvitationMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_invite_accept_and_sign_in(): void
    {
        Mail::fake();
        $manager = $this->actingAsRole();

        $this->postJson('/api/portal/staff', ['name' => 'Ruth Namusoke', 'email' => 'Ruth@Hotel.test', 'role' => 'guest'])->assertUnprocessable();
        $this->postJson('/api/portal/staff', ['name' => 'Ruth Namusoke', 'email' => 'Ruth@Hotel.test', 'role' => 'front_desk'])
            ->assertCreated()->assertJsonPath('data.invite_pending', true)->assertJsonPath('data.email', 'ruth@hotel.test');
        $this->postJson('/api/portal/staff', ['name' => 'Again', 'email' => 'ruth@hotel.test', 'role' => 'front_desk'])->assertStatus(409);

        $url = null;
        Mail::assertSent(StaffInvitationMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return $m->hasTo('ruth@hotel.test');
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->app['auth']->forgetGuards(); // the new staff member opens the link signed out

        $this->postJson('/api/auth/accept-invite', ['email' => 'ruth@hotel.test', 'token' => 'wrong', 'password' => 'Kampala2026', 'password_confirmation' => 'Kampala2026'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/accept-invite', ['email' => 'ruth@hotel.test', 'token' => $q['token'], 'password' => 'Kampala2026', 'password_confirmation' => 'Kampala2026'])
            ->assertOk()->assertJsonPath('data.user.role', 'front_desk')->assertJsonStructure(['data' => ['token']]);
        // One-time link.
        $this->postJson('/api/auth/accept-invite', ['email' => 'ruth@hotel.test', 'token' => $q['token'], 'password' => 'Kampala2026', 'password_confirmation' => 'Kampala2026'])
            ->assertUnprocessable();

        $this->assertSame(1, $manager->notifications()->count()); // "Ruth joined the portal"
        Sanctum::actingAs($manager);
        $this->getJson('/api/portal/staff?filter[role]=front_desk')->assertOk()->assertJsonPath('data.0.invite_pending', false);
    }

    public function test_deactivate_change_role_and_guards(): void
    {
        $manager = $this->actingAsRole();
        $clerk = User::factory()->create();
        $clerk->forceFill(['role' => UserRole::FrontDesk])->save();
        $clerk->createToken('portal');

        $this->patchJson("/api/portal/staff/{$manager->id}", ['is_active' => false])->assertUnprocessable(); // not yourself
        $this->patchJson("/api/portal/staff/{$clerk->id}", ['role' => 'housekeeping'])->assertOk()->assertJsonPath('data.role', 'housekeeping');
        $this->assertSame(0, $clerk->tokens()->count()); // signed out everywhere

        $this->patchJson("/api/portal/staff/{$clerk->id}", ['is_active' => false])->assertOk();
        $this->postJson('/api/auth/login', ['email' => $clerk->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'deactivated'));

        $this->actingAsRole(UserRole::FrontDesk);
        $this->getJson('/api/portal/staff')->assertForbidden();
    }

    public function test_managers_can_change_another_managers_role(): void
    {
        $this->actingAsRole();
        $other = User::factory()->create();
        $other->forceFill(['role' => UserRole::Manager])->save();

        $this->patchJson("/api/portal/staff/{$other->id}", ['role' => 'owner'])->assertOk()->assertJsonPath('data.role', 'owner');
        $this->patchJson("/api/portal/staff/{$other->id}", ['name' => ''])->assertUnprocessable();
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'grace@hotel.test']);
        $user->forceFill(['role' => UserRole::Manager])->save();

        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@hotel.test'])->assertOk(); // no account leak
        $this->postJson('/api/auth/forgot-password', ['email' => 'grace@hotel.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token, $user) {
            $token = $n->token;

            return str_contains($n->toMail($user)->actionUrl, '/portal/reset-password?');
        });

        $this->postJson('/api/auth/reset-password', ['email' => 'grace@hotel.test', 'token' => $token, 'password' => 'NewPass2026', 'password_confirmation' => 'NewPass2026'])->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'grace@hotel.test', 'password' => 'NewPass2026'])->assertOk();
    }
}
