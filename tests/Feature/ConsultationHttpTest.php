<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationHttpTest extends TestCase
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

    public function test_receptionist_opens_an_encounter(): void
    {
        $h = Hospital::factory()->create();
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($this->user($h, 'receptionist'))->post('/admin/consultations', [
            'patient_id' => $patient->id, 'reason' => 'Fever',
        ])->assertRedirect();

        $this->assertDatabaseHas('consultations', ['patient_id' => $patient->id, 'status' => 'registration']);
    }

    public function test_nurse_records_vitals_and_bmi_is_computed(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($nurse)->post("/admin/consultations/{$c->uuid}/vitals", [
            'weight' => 70, 'height' => 175, 'temperature' => 37.0,
        ])->assertRedirect();

        $this->assertSame('22.86', (string) $c->fresh()->bmi); // 70/1.75^2
    }

    public function test_nurse_cannot_write_diagnosis(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($nurse)->post("/admin/consultations/{$c->uuid}/clinical", [
            'diagnosis' => 'Malaria',
        ])->assertForbidden();
        $this->assertNull($c->fresh()->diagnosis);
    }

    public function test_doctor_writes_diagnosis(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->user($h, 'doctor');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($doctor)->post("/admin/consultations/{$c->uuid}/clinical", [
            'diagnosis' => 'Malaria', 'doctor_remarks' => 'ACT prescribed',
        ])->assertRedirect();
        $this->assertSame('Malaria', $c->fresh()->diagnosis);
    }

    public function test_illegal_transition_flashes_error(): void
    {
        $h = Hospital::factory()->create();
        $recep = $this->user($h, 'receptionist');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($recep)->post("/admin/consultations/{$c->uuid}/transition", ['status' => 'completed'])
            ->assertSessionHas('error');
        $this->assertSame(ConsultationStatus::Registration, $c->fresh()->status);
    }
}
