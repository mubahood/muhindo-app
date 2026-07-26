<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `settings` table is PLATFORM-GLOBAL (no hospital_id) — it drives the
 * public site name/tagline/verify rate-limit shared by every tenant. A tenant
 * hospital_admin must never be able to read or write it; only the SaaS operator
 * (super-admin) may. (Per-hospital config lives in BillingSettingController.)
 */
class GlobalSettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function make(string $role, ?int $hospitalId): User
    {
        $u = User::factory()->create(['role' => $role, 'hospital_id' => $hospitalId]);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_hospital_admin_cannot_view_global_site_settings(): void
    {
        $admin = $this->make('hospital_admin', Hospital::factory()->create()->id);

        $this->actingAs($admin)->get('/admin/settings')->assertForbidden();
    }

    public function test_hospital_admin_cannot_write_global_site_settings(): void
    {
        $admin = $this->make('hospital_admin', Hospital::factory()->create()->id);

        $this->actingAs($admin)->post('/admin/settings', [
            'site_name' => 'Hijacked', 'tagline' => 'x', 'default_theme' => 'light', 'verify_rate_limit' => 5,
        ])->assertForbidden();

        $this->assertDatabaseMissing('settings', ['value' => 'Hijacked']);
    }

    public function test_super_admin_can_manage_global_site_settings(): void
    {
        $super = $this->make('super_admin', null);

        $this->actingAs($super)->get('/admin/settings')->assertOk();

        $this->actingAs($super)->post('/admin/settings', [
            'site_name' => 'True-Doctor', 'tagline' => 'Care, connected',
            'default_theme' => 'light', 'verify_rate_limit' => 30,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'True-Doctor']);
    }
}
