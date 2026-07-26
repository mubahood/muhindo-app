<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function admin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_create_a_profile_for_a_staff_user(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->admin($h);
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);

        $this->actingAs($admin)->post('/admin/staff', [
            'user_id' => $doctor->id, 'specialty' => 'Cardiology', 'license_no' => 'LIC-99', 'is_active' => 1,
        ])->assertRedirect('/admin/staff');

        $this->assertDatabaseHas('staff_profiles', ['hospital_id' => $h->id, 'user_id' => $doctor->id, 'specialty' => 'Cardiology']);
    }

    public function test_one_profile_per_user(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->admin($h);
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        StaffProfile::factory()->create(['hospital_id' => $h->id, 'user_id' => $doctor->id]);

        $this->actingAs($admin)->post('/admin/staff', ['user_id' => $doctor->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_cannot_link_a_user_from_another_hospital(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $doctorB = User::factory()->create(['hospital_id' => $b->id, 'role' => 'doctor']);

        $this->actingAs($this->admin($a))->post('/admin/staff', ['user_id' => $doctorB->id])
            ->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('staff_profiles', 0);
    }

    public function test_profiles_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $userB = User::factory()->create(['hospital_id' => $b->id, 'role' => 'nurse', 'name' => 'NurseB']);
        $profileB = StaffProfile::factory()->create(['hospital_id' => $b->id, 'user_id' => $userB->id]);

        $this->actingAs($this->admin($a))->get('/admin/staff')->assertDontSee('NurseB');
        $this->actingAs($this->admin($a))->get("/admin/staff/{$profileB->id}/edit")->assertNotFound();
    }
}
