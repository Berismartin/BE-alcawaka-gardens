<?php

namespace Tests;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /** Signs in a staff member with the given role and returns them. */
    protected function actingAsRole(UserRole $role = UserRole::Manager): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);

        return $user;
    }

    /** Money and booking mutations require an Idempotency-Key. */
    protected function idempotent(): static
    {
        return $this->withHeader('Idempotency-Key', (string) Str::uuid());
    }
}
