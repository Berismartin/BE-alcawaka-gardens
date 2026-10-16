<?php

namespace Tests\Feature\Audit;

use App\Domains\Users\User\Enums\UserRole;
use App\Models\Facilities\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function as(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_facility_changes_are_recorded_with_a_diff(): void
    {
        $manager = $this->as(UserRole::Manager);
        $facility = Facility::factory()->create(['opening_hours' => '08:00 – 18:00']);

        $this->patchJson("/api/portal/facilities/{$facility->id}", ['opening_hours' => '07:00 – 19:00'])->assertOk();

        $this->getJson("/api/portal/audit-logs?filter[subject_type]=facility&filter[subject_id]={$facility->id}")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'facility.updated')
            ->assertJsonPath('data.0.user.name', $manager->name)
            ->assertJsonPath('data.0.changes.opening_hours', ['from' => '08:00 – 18:00', 'to' => '07:00 – 19:00']);
    }

    public function test_only_roles_with_audit_view_can_read_the_log(): void
    {
        $this->as(UserRole::Owner);
        $this->getJson('/api/portal/audit-logs')->assertOk();

        $this->as(UserRole::FrontDesk);
        $this->getJson('/api/portal/audit-logs')->assertForbidden();
    }
}
