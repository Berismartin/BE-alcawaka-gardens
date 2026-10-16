<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_that_works_for_me_and_logout(): void
    {
        User::factory()->create(['email' => 'grace@alcawaka.test']);

        $token = $this->postJson('/api/auth/login', ['email' => 'Grace@alcawaka.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'guest')
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'grace@alcawaka.test');
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    }

    public function test_wrong_password_is_rejected_without_revealing_which_field_failed(): void
    {
        User::factory()->create(['email' => 'grace@alcawaka.test']);

        $this->postJson('/api/auth/login', ['email' => 'grace@alcawaka.test', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('email');
    }
}
