<?php

namespace Tests\Feature\Guests;

use App\Domains\Guests\Guest\ValueObjects\PhoneNumber;
use App\Domains\Users\User\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_numbers_are_normalised(): void
    {
        $this->assertSame('+256772123456', PhoneNumber::normalize('0772 123 456'));
        $this->assertSame('+256772123456', PhoneNumber::normalize('256772123456'));
        $this->assertSame('+256772123456', PhoneNumber::normalize('+256 772-123-456'));
        $this->assertSame('+447700900123', PhoneNumber::normalize('0044 7700 900123'));
        $this->assertNull(PhoneNumber::normalize(' '));
    }

    public function test_front_desk_manages_guests(): void
    {
        $this->actingAsRole(UserRole::FrontDesk);

        $id = $this->postJson('/api/portal/guests', ['full_name' => 'Amina Nakato', 'phone' => '0772 123 456', 'email' => 'Amina@Example.com'])
            ->assertCreated()->assertJsonPath('data.phone', '+256772123456')->assertJsonPath('data.email', 'amina@example.com')
            ->json('data.id');

        $this->getJson('/api/portal/guests?q=nakato')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson("/api/portal/guests/{$id}", ['phone' => '12'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->patchJson("/api/portal/guests/{$id}", ['notes' => 'Prefers a quiet room'])->assertOk()->assertJsonPath('data.notes', 'Prefers a quiet room');
        $this->deleteJson("/api/portal/guests/{$id}")->assertOk();

        $this->actingAsRole(UserRole::Housekeeping);
        $this->getJson('/api/portal/guests')->assertForbidden();
    }
}
