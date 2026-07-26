<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function user(Hospital $h, string $role = 'hospital_admin'): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => $role]);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_admin_creates_a_department(): void
    {
        $h = Hospital::factory()->create();

        $this->actingAs($this->user($h))->post('/admin/departments', [
            'name' => 'Outpatient', 'code' => 'opd', 'is_active' => 1,
        ])->assertRedirect('/admin/departments');

        $this->assertDatabaseHas('departments', ['hospital_id' => $h->id, 'name' => 'Outpatient', 'code' => 'OPD']);
    }

    public function test_name_is_unique_per_hospital_but_reusable_across_hospitals(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        Department::factory()->create(['hospital_id' => $a->id, 'name' => 'Pharmacy', 'code' => 'PH']);

        // Same name in A → rejected.
        $this->actingAs($this->user($a))->post('/admin/departments', ['name' => 'Pharmacy'])
            ->assertSessionHasErrors('name');

        // Same name in B → allowed (different tenant).
        $this->actingAs($this->user($b))->post('/admin/departments', ['name' => 'Pharmacy'])
            ->assertRedirect('/admin/departments');
        $this->assertDatabaseCount('departments', 2);
    }

    public function test_a_role_without_departments_manage_cannot_create(): void
    {
        $h = Hospital::factory()->create();

        $this->actingAs($this->user($h, 'doctor'))->post('/admin/departments', ['name' => 'X'])
            ->assertForbidden();
        $this->assertDatabaseCount('departments', 0);
    }

    public function test_head_of_department_dropdown_only_lists_this_hospitals_users(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $mine = User::factory()->create(['hospital_id' => $a->id, 'name' => 'MyOwnDoctor', 'role' => 'doctor']);
        $foreign = User::factory()->create(['hospital_id' => $b->id, 'name' => 'ForeignDoctor', 'role' => 'doctor']);

        $res = $this->actingAs($this->user($a))->get('/admin/departments/create');
        $res->assertOk()->assertSee('MyOwnDoctor')->assertDontSee('ForeignDoctor');

        // Defence in depth: even a forged pick of a foreign user is rejected.
        $this->actingAs($this->user($a))->post('/admin/departments', [
            'name' => 'Cardiology', 'head_user_id' => $foreign->id,
        ])->assertSessionHasErrors('head_user_id');
    }

    public function test_departments_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $deptB = Department::factory()->create(['hospital_id' => $b->id, 'name' => 'SecretDept']);

        // A cannot list or edit B's department.
        $this->actingAs($this->user($a))->get('/admin/departments')->assertDontSee('SecretDept');
        $this->actingAs($this->user($a))->get("/admin/departments/{$deptB->id}/edit")->assertNotFound();
        $this->actingAs($this->user($a))->put("/admin/departments/{$deptB->id}", ['name' => 'Hacked'])->assertNotFound();

        $this->assertSame('SecretDept', Department::withoutGlobalScopes()->find($deptB->id)->name);
    }
}
