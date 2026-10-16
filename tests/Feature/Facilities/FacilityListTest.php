<?php

namespace Tests\Feature\Facilities;

use App\Domains\Facilities\Facility\Enums\FacilityAccess;
use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacilityListTest extends TestCase
{
    use RefreshDatabase;

    private function actingAs_(UserRole $role): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);
    }

    public function test_list_is_paginated_with_meta(): void
    {
        $this->actingAs_(UserRole::Manager);
        Facility::factory()->count(12)->create();

        $this->getJson('/api/portal/facilities?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 10, 'total' => 12, 'last_page' => 2]);
    }

    public function test_search_filter_and_sort(): void
    {
        $this->actingAs_(UserRole::Manager);
        Facility::factory()->create(['name' => 'Swimming pool', 'access' => FacilityAccess::Paid, 'price_ugx' => 20000]);
        Facility::factory()->create(['name' => 'Kids pool', 'access' => FacilityAccess::FreeForGuests]);
        Facility::factory()->create(['name' => 'Gardens', 'access' => FacilityAccess::Paid, 'price_ugx' => 300000, 'is_active' => false]);

        $this->getJson('/api/portal/facilities?q=pool&sort=-name')
            ->assertJsonPath('data.*.name', ['Swimming pool', 'Kids pool']);

        $this->getJson('/api/portal/facilities?filter[access]=paid&sort=-price_ugx')
            ->assertJsonPath('data.*.name', ['Gardens', 'Swimming pool']);

        $this->getJson('/api/portal/facilities?filter[is_active]=false')
            ->assertJsonPath('data.*.name', ['Gardens']);
    }

    public function test_unknown_sort_filter_or_page_size_is_rejected(): void
    {
        $this->actingAs_(UserRole::Manager);

        $this->getJson('/api/portal/facilities?sort=password')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/portal/facilities?filter[slug]=x')->assertUnprocessable()->assertJsonValidationErrors('filter');
        $this->getJson('/api/portal/facilities?per_page=5000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_permissions_matrix(): void
    {
        $facility = Facility::factory()->create();

        $this->actingAs_(UserRole::FrontDesk);
        $this->getJson('/api/portal/facilities')->assertOk();
        $this->deleteJson("/api/portal/facilities/{$facility->id}")->assertForbidden()->assertJsonPath('success', false);

        $this->actingAs_(UserRole::Housekeeping);
        $this->getJson('/api/portal/facilities')->assertForbidden();
    }
}
