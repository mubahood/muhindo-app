<?php

namespace Tests\Feature;

use App\Enums\DoseRecordStatus;
use App\Models\Consultation;
use App\Models\DoseItemRecord;
use App\Models\Hospital;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrescriptionHttpTest extends TestCase
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

    public function test_doctor_prescribes_and_schedule_is_generated(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->user($h, 'doctor');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($doctor)->post("/admin/consultations/{$c->uuid}/prescriptions", [
            'notes' => 'Course of antibiotics',
            'items' => [[
                'drug_name' => 'Amoxicillin', 'dosage' => '500mg',
                'slots' => ['morning', 'night'], 'days' => 3, 'start_date' => now()->toDateString(),
            ]],
        ])->assertRedirect();

        $rx = Prescription::firstOrFail();
        $this->assertSame(1, $rx->doseItems()->count());
        $this->assertSame(6, DoseItemRecord::count()); // 2 slots × 3 days
    }

    public function test_nurse_cannot_prescribe(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($nurse)->post("/admin/consultations/{$c->uuid}/prescriptions", [
            'items' => [['drug_name' => 'X', 'slots' => ['morning'], 'days' => 1]],
        ])->assertForbidden();
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_nurse_administers_a_dose_but_doctor_cannot(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->user($h, 'doctor');
        $nurse = $this->user($h, 'nurse');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);
        $this->actingAs($doctor)->post("/admin/consultations/{$c->uuid}/prescriptions", [
            'items' => [['drug_name' => 'Amox', 'slots' => ['morning'], 'days' => 1]],
        ]);
        $rec = DoseItemRecord::firstOrFail();

        $this->actingAs($doctor)->post("/admin/dose-records/{$rec->id}/administer", ['status' => 'administered'])->assertForbidden();

        $this->actingAs($nurse)->post("/admin/dose-records/{$rec->id}/administer", ['status' => 'administered'])->assertRedirect();
        $this->assertSame(DoseRecordStatus::Administered, $rec->fresh()->status);
        $this->assertSame($nurse->id, $rec->fresh()->administered_by);
    }

    public function test_prescription_is_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $consultB = Consultation::factory()->create(['hospital_id' => $b->id]);
        $doctorA = $this->user($a, 'doctor');

        $this->actingAs($doctorA)->post("/admin/consultations/{$consultB->uuid}/prescriptions", [
            'items' => [['drug_name' => 'X', 'slots' => ['morning'], 'days' => 1]],
        ])->assertNotFound();
        $this->assertDatabaseCount('prescriptions', 0);
    }
}
