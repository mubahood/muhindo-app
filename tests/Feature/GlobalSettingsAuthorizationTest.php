<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Site settings are global (site name, tagline, contacts), only the owner (super_admin) may change them. */
class GlobalSettingsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function make(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'is_admin' => $role === 'super_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_admin_cannot_view_global_site_settings(): void
    {
        $admin = $this->make('admin');

        $this->actingAs($admin)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_cannot_write_global_site_settings(): void
    {
        $admin = $this->make('admin');

        $this->actingAs($admin)->post('/admin/settings', [
            'site_name' => 'Hijacked', 'tagline' => 'x', 'default_theme' => 'light',
        ])->assertForbidden();

        $this->assertDatabaseMissing('settings', ['value' => 'Hijacked']);
    }

    public function test_super_admin_can_manage_global_site_settings(): void
    {
        $super = $this->make('super_admin');

        $this->actingAs($super)->get('/admin/settings')->assertOk();

        $this->actingAs($super)->post('/admin/settings', [
            'site_name' => 'Muhindo Mubaraka', 'tagline' => 'Systems that work',
            'default_theme' => 'light',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('settings', ['key' => 'site_name', 'value' => 'Muhindo Mubaraka']);
    }
}
