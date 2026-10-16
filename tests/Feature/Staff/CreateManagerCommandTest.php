<?php

namespace Tests\Feature\Staff;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateManagerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_manager_with_a_strong_password(): void
    {
        $this->artisan('staff:create-manager', ['email' => 'grace@alcawaka.ug', 'name' => 'Grace Atim'])
            ->expectsQuestion('Password (at least 10 characters)', 'short')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'grace@alcawaka.ug']);

        $this->artisan('staff:create-manager', ['email' => 'grace@alcawaka.ug', 'name' => 'Grace Atim'])
            ->expectsQuestion('Password (at least 10 characters)', 'Gardens2026x')
            ->assertSuccessful();
        $user = User::where('email', 'grace@alcawaka.ug')->firstOrFail();
        $this->assertSame(UserRole::Manager, $user->role);
        $this->assertTrue(Hash::check('Gardens2026x', $user->password));

        $this->artisan('staff:create-manager', ['email' => 'grace@alcawaka.ug', 'name' => 'Again'])
            ->expectsQuestion('Password (at least 10 characters)', 'Gardens2026x')
            ->assertFailed();
    }
}
