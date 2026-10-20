<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_defaults_and_partial_updates_merge(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/auth/me')
            ->assertJsonPath('data.preferences', ['mode' => 'system', 'accent' => 'forest', 'sidebar_collapsed' => false, 'table_density' => 'comfortable', 'tours_off' => false, 'tours_seen' => []])
            ->assertJsonPath('data.permissions', []);

        $this->patchJson('/api/auth/me/preferences', ['accent' => 'ocean'])->assertOk();
        $this->patchJson('/api/auth/me/preferences', ['mode' => 'dark'])
            ->assertJsonPath('data.accent', 'ocean')
            ->assertJsonPath('data.mode', 'dark');
    }

    public function test_unknown_values_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/auth/me/preferences', ['accent' => 'neon'])->assertUnprocessable();

        // Guided tours: remembered per account; anything that isn't a tour name is refused.
        $this->patchJson('/api/auth/me/preferences', ['tours_seen' => ['welcome', 'portal-calendar']])->assertOk()
            ->assertJsonPath('data.tours_seen', ['welcome', 'portal-calendar'])->assertJsonPath('data.tours_off', false);
        $this->patchJson('/api/auth/me/preferences', ['tours_off' => true])->assertOk()->assertJsonPath('data.tours_seen.1', 'portal-calendar');
        $this->patchJson('/api/auth/me/preferences', ['tours_seen' => ['<script>']])->assertUnprocessable();
    }
}
