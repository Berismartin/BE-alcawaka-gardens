<?php

namespace Tests\Feature\Facilities;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsStaff(UserRole $role = UserRole::Manager): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);
    }

    public function test_public_list_shows_only_active_facilities(): void
    {
        Facility::factory()->create(['name' => 'Swimming pool']);
        Facility::factory()->create(['name' => 'Old sauna', 'is_active' => false]);

        $this->getJson('/api/facilities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Swimming pool');
    }

    public function test_guests_and_anonymous_users_cannot_manage_facilities(): void
    {
        $this->postJson('/api/portal/facilities', [])->assertUnauthorized()->assertJsonPath('success', false);

        $this->actingAsStaff(UserRole::Guest);
        $this->postJson('/api/portal/facilities', ['name' => 'Gym', 'access' => 'free_for_guests'])->assertForbidden();
    }

    public function test_staff_can_create_update_and_delete_a_facility(): void
    {
        $this->actingAsStaff();

        $id = $this->postJson('/api/portal/facilities', [
            'name' => 'Swimming Pool',
            'access' => 'paid',
            'price_ugx' => 20000,
            'capacity' => 30,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'swimming-pool')
            ->assertJsonPath('data.price_ugx', 20000)
            ->json('data.id');

        $this->patchJson("/api/portal/facilities/{$id}", ['opening_hours' => '07:00 – 19:00'])
            ->assertOk()
            ->assertJsonPath('data.opening_hours', '07:00 – 19:00')
            ->assertJsonPath('data.price_ugx', 20000);

        $this->deleteJson("/api/portal/facilities/{$id}")->assertOk();
        $this->getJson("/api/portal/facilities/{$id}")->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_paid_facility_requires_a_price(): void
    {
        $this->actingAsStaff();

        $this->postJson('/api/portal/facilities', ['name' => 'Gym', 'access' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price_ugx');

        $free = Facility::factory()->create();
        $this->patchJson("/api/portal/facilities/{$free->id}", ['access' => 'reservation'])
            ->assertUnprocessable();
    }

    public function test_duplicate_names_are_rejected(): void
    {
        $this->actingAsStaff();
        Facility::factory()->create(['name' => 'Gardens', 'slug' => 'gardens']);

        $this->postJson('/api/portal/facilities', ['name' => 'Gardens', 'access' => 'free_for_guests'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }
}
