<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function user(Hospital $h, string $role): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => $role]);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_admin_adds_a_service_to_the_price_list(): void
    {
        $h = Hospital::factory()->create();

        $this->actingAs($this->user($h, 'hospital_admin'))->post('/admin/services', [
            'name' => 'Wound dressing', 'price' => '15.00', 'tax_exempt' => 0, 'is_active' => 1,
        ])->assertRedirect('/admin/services');

        $this->assertDatabaseHas('services', ['hospital_id' => $h->id, 'name' => 'Wound dressing', 'price' => '15.00']);
    }

    public function test_name_unique_per_hospital_reusable_across(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        Service::factory()->create(['hospital_id' => $a->id, 'name' => 'X-ray']);

        $this->actingAs($this->user($a, 'hospital_admin'))->post('/admin/services', ['name' => 'X-ray', 'price' => '1'])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->user($b, 'hospital_admin'))->post('/admin/services', ['name' => 'X-ray', 'price' => '1'])
            ->assertRedirect('/admin/services');
    }

    public function test_role_without_services_manage_cannot_create(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->user($h, 'nurse'))->post('/admin/services', ['name' => 'Y', 'price' => '1'])
            ->assertForbidden();
    }

    public function test_price_list_is_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $svcB = Service::factory()->create(['hospital_id' => $b->id, 'name' => 'SecretSvc']);

        $this->actingAs($this->user($a, 'hospital_admin'))->get('/admin/services')->assertDontSee('SecretSvc');
        $this->actingAs($this->user($a, 'hospital_admin'))->get("/admin/services/{$svcB->id}/edit")->assertNotFound();
    }
}
