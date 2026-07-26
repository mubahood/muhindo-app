<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_inline_intake_registers_patient_and_opens_encounter(): void
    {
        $h = Hospital::factory()->create();
        $recep = User::factory()->create(['hospital_id' => $h->id, 'role' => 'receptionist']);
        $recep->syncSpatieRole();

        $this->actingAs($recep)->post('/admin/consultations/intake', [
            'first_name' => 'Grace', 'last_name' => 'Newcomer', 'sex' => 'female',
            'phone_1' => '0700000000', 'consent_given' => 1, 'reason' => 'Headache',
        ])->assertRedirect();

        $patient = Patient::where('first_name', 'Grace')->firstOrFail();
        $this->assertSame($h->id, $patient->hospital_id);
        $this->assertNotNull($patient->patient_no);

        $consultation = Consultation::firstOrFail();
        $this->assertSame($patient->id, $consultation->patient_id);
        $this->assertSame('Headache', $consultation->reason);
    }

    public function test_intake_is_atomic_on_invalid_input(): void
    {
        $h = Hospital::factory()->create();
        $recep = User::factory()->create(['hospital_id' => $h->id, 'role' => 'receptionist']);
        $recep->syncSpatieRole();

        // Missing last_name → validation fails, nothing created.
        $this->actingAs($recep)->post('/admin/consultations/intake', [
            'first_name' => 'Half', 'reason' => 'X',
        ])->assertSessionHasErrors('last_name');

        $this->assertDatabaseCount('patients', 0);
        $this->assertDatabaseCount('consultations', 0);
    }
}
