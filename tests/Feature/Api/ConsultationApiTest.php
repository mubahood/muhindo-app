<?php

namespace Tests\Feature\Api;

use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultationApiTest extends TestCase
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

    public function test_open_encounter_and_get_number(): void
    {
        $h = Hospital::factory()->create();
        app(CurrentHospital::class)->set($h->id);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        Sanctum::actingAs($this->staff($h, 'receptionist'));

        $res = $this->postJson('/api/v1/consultations', ['patient_id' => $patient->id, 'reason' => 'Fever']);

        $res->assertStatus(201)->assertJsonPath('data.status', 'registration');
        $this->assertMatchesRegularExpression('/^C-\d{8}-\d{3}$/', $res->json('data.consultation_no'));
    }

    public function test_nurse_records_vitals_bmi_computed(): void
    {
        $h = Hospital::factory()->create();
        app(CurrentHospital::class)->set($h->id);
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);
        Sanctum::actingAs($this->staff($h, 'nurse'));

        $res = $this->postJson("/api/v1/consultations/{$c->uuid}/vitals", ['weight' => 80, 'height' => 178]);
        $res->assertOk()->assertJsonPath('data.vitals.bmi', '25.25');
    }

    public function test_nurse_cannot_diagnose_but_doctor_can(): void
    {
        $h = Hospital::factory()->create();
        app(CurrentHospital::class)->set($h->id);
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        Sanctum::actingAs($this->staff($h, 'nurse'));
        $this->postJson("/api/v1/consultations/{$c->uuid}/clinical", ['diagnosis' => 'Malaria'])->assertStatus(403);

        Sanctum::actingAs($this->staff($h, 'doctor'));
        $this->postJson("/api/v1/consultations/{$c->uuid}/clinical", ['diagnosis' => 'Malaria'])
            ->assertOk()->assertJsonPath('data.diagnosis', 'Malaria');
    }

    public function test_illegal_transition_returns_422(): void
    {
        $h = Hospital::factory()->create();
        app(CurrentHospital::class)->set($h->id);
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);
        Sanctum::actingAs($this->staff($h, 'receptionist'));

        $this->postJson("/api/v1/consultations/{$c->uuid}/transition", ['status' => 'completed'])
            ->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_cross_hospital_consultation_is_404(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $cB = Consultation::factory()->create(['hospital_id' => $b->id]);
        Sanctum::actingAs($this->staff($a, 'doctor'));

        $this->getJson("/api/v1/consultations/{$cB->uuid}")->assertStatus(404);
    }
}
