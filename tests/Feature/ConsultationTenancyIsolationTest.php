<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §2.1 — hospital A can neither see nor act on hospital B's encounters. */
class ConsultationTenancyIsolationTest extends TestCase
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

    public function test_a_cannot_see_or_act_on_bs_consultation(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $patientB = Patient::factory()->create(['hospital_id' => $b->id, 'first_name' => 'Zeb', 'last_name' => 'FromB']);
        $consultB = Consultation::factory()->create(['hospital_id' => $b->id, 'patient_id' => $patientB->id]);
        $adminA = $this->admin($a);

        $this->actingAs($adminA)->get('/admin/consultations')->assertOk()->assertDontSee('FromB');
        $this->actingAs($adminA)->get("/admin/consultations/{$consultB->uuid}")->assertNotFound();
        $this->actingAs($adminA)->post("/admin/consultations/{$consultB->uuid}/vitals", ['weight' => 50])->assertNotFound();
        $this->actingAs($adminA)->post("/admin/consultations/{$consultB->uuid}/transition", ['status' => 'cancelled'])->assertNotFound();

        $this->assertSame('registration', Consultation::withoutGlobalScopes()->find($consultB->id)->status->value);
    }

    public function test_cannot_open_encounter_for_another_hospitals_patient(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $patientB = Patient::factory()->create(['hospital_id' => $b->id]);

        $this->actingAs($this->admin($a))->post('/admin/consultations', ['patient_id' => $patientB->id])
            ->assertSessionHasErrors('patient_id');
        $this->assertDatabaseCount('consultations', 0);
    }
}
