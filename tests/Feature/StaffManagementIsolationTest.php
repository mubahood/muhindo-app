<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * HMS_PLAN.md §2.1 — "every new tenant-scoped feature ships with an
 * isolation test". UserController::scoped() is the explicit tenancy check
 * here (User can't use the BelongsToHospital global scope — super-admin
 * accounts with hospital_id=null must coexist with hospital-scoped ones).
 */
class StaffManagementIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function hospitalAdmin(Hospital $hospital): User
    {
        $u = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_hospital_a_cannot_list_hospital_bs_staff(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();
        $adminA = $this->hospitalAdmin($hospitalA);
        $staffB = User::factory()->create(['hospital_id' => $hospitalB->id, 'name' => 'Nurse B']);

        $response = $this->actingAs($adminA)->get('/admin/users');

        $response->assertOk();
        $response->assertDontSee('Nurse B');
    }

    public function test_hospital_a_cannot_view_or_edit_hospital_bs_staff_by_id(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();
        $adminA = $this->hospitalAdmin($hospitalA);
        $staffB = User::factory()->create(['hospital_id' => $hospitalB->id]);

        $this->actingAs($adminA)->get("/admin/users/{$staffB->id}")->assertNotFound();
        $this->actingAs($adminA)->get("/admin/users/{$staffB->id}/edit")->assertNotFound();

        $this->actingAs($adminA)->put("/admin/users/{$staffB->id}", [
            'name' => 'Tampered', 'email' => $staffB->email, 'role' => 'nurse', 'is_active' => 1,
        ])->assertNotFound();
        $this->assertNotSame('Tampered', $staffB->fresh()->name);
    }

    public function test_a_new_staff_member_is_auto_assigned_to_the_creating_admins_hospital(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();
        $adminA = $this->hospitalAdmin($hospitalA);

        $this->actingAs($adminA)->post('/admin/users', [
            'name' => 'New Nurse', 'email' => 'nurse@a.test', 'role' => 'nurse', 'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'nurse@a.test')->firstOrFail();
        $this->assertSame($hospitalA->id, $created->hospital_id);
        $this->assertNotSame($hospitalB->id, $created->hospital_id);
    }

    public function test_super_admin_sees_staff_across_every_hospital(): void
    {
        $hospitalA = Hospital::factory()->create();
        $hospitalB = Hospital::factory()->create();
        User::factory()->create(['hospital_id' => $hospitalA->id, 'name' => 'Staff A']);
        User::factory()->create(['hospital_id' => $hospitalB->id, 'name' => 'Staff B']);

        $superAdmin = User::factory()->create(['hospital_id' => null, 'role' => 'super_admin', 'is_admin' => true]);
        $superAdmin->syncSpatieRole();

        $response = $this->actingAs($superAdmin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee('Staff A');
        $response->assertSee('Staff B');
    }
}
