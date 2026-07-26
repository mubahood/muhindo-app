<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function staff(Hospital $h, string $role): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => $role]);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_receptionist_registers_a_patient_scoped_to_their_hospital(): void
    {
        $h = Hospital::factory()->create();
        $receptionist = $this->staff($h, 'receptionist');

        $this->actingAs($receptionist)->post('/admin/patients', [
            'first_name' => 'Grace', 'last_name' => 'Achieng', 'sex' => 'female',
            'phone_1' => '+256700000001', 'consent_given' => 1,
        ])->assertRedirect();

        $p = Patient::withoutGlobalScopes()->where('first_name', 'Grace')->firstOrFail();
        $this->assertSame($h->id, $p->hospital_id);
        $this->assertStringStartsWith('PT-'.date('Y').'-', $p->patient_no);
        $this->assertSame($receptionist->id, $p->registered_by);
        $this->assertNotNull($p->consent_at);
    }

    public function test_patient_numbers_increment_per_hospital(): void
    {
        $h = Hospital::factory()->create();
        $r = $this->staff($h, 'receptionist');

        $this->actingAs($r)->post('/admin/patients', ['first_name' => 'A', 'last_name' => 'One', 'consent_given' => 1]);
        $this->actingAs($r)->post('/admin/patients', ['first_name' => 'B', 'last_name' => 'Two', 'consent_given' => 1]);

        $nos = Patient::withoutGlobalScopes()->orderBy('id')->pluck('patient_no')->all();
        $this->assertStringStartsWith('PT-'.date('Y').'-000001', $nos[0]);
        $this->assertStringStartsWith('PT-'.date('Y').'-000002', $nos[1]);
    }

    public function test_a_role_without_create_permission_cannot_register(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->staff($h, 'doctor'); // has patients.view/update, not create

        $this->actingAs($doctor)->post('/admin/patients', [
            'first_name' => 'X', 'last_name' => 'Y', 'consent_given' => 1,
        ])->assertForbidden();

        $this->assertSame(0, Patient::withoutGlobalScopes()->count());
    }

    public function test_validation_rejects_a_patient_with_no_name(): void
    {
        $h = Hospital::factory()->create();
        $r = $this->staff($h, 'receptionist');

        $this->actingAs($r)->post('/admin/patients', ['first_name' => '', 'last_name' => ''])
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }
}
