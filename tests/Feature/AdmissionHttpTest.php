<?php

namespace Tests\Feature;

use App\Enums\BedStatus;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Services\AdmissionService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionHttpTest extends TestCase
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

    private function bed(Hospital $h): Bed
    {
        app(CurrentHospital::class)->set($h->id);
        $ward = Ward::factory()->create(['hospital_id' => $h->id]);

        return Bed::factory()->create(['hospital_id' => $h->id, 'ward_id' => $ward->id]);
    }

    public function test_nurse_admits_transfers_and_discharges(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $bedA = $this->bed($h);
        $bedB = $this->bed($h);

        $this->actingAs($nurse)->post('/admin/admissions', [
            'patient_id' => $patient->id, 'bed_id' => $bedA->id, 'reason' => 'Obs',
        ])->assertRedirect();
        $admission = Admission::firstOrFail();
        $this->assertSame(BedStatus::Occupied, $bedA->fresh()->status);

        $this->actingAs($nurse)->post("/admin/admissions/{$admission->uuid}/transfer", ['to_bed_id' => $bedB->id])->assertRedirect();
        $this->assertSame(BedStatus::Available, $bedA->fresh()->status);
        $this->assertSame(BedStatus::Occupied, $bedB->fresh()->status);

        $this->actingAs($nurse)->post("/admin/admissions/{$admission->uuid}/discharge", ['outcome' => 'discharged'])->assertRedirect();
        $this->assertSame('discharged', $admission->fresh()->status->value);
        $this->assertSame(BedStatus::Available, $bedB->fresh()->status);
    }

    public function test_cannot_admit_to_an_occupied_bed_via_http(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $bed = $this->bed($h);
        $p1 = Patient::factory()->create(['hospital_id' => $h->id]);
        $p2 = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($nurse)->post('/admin/admissions', ['patient_id' => $p1->id, 'bed_id' => $bed->id]);
        $this->actingAs($nurse)->post('/admin/admissions', ['patient_id' => $p2->id, 'bed_id' => $bed->id])
            ->assertSessionHas('error');
        $this->assertSame(1, Admission::count());
    }

    public function test_receptionist_cannot_admit(): void
    {
        $h = Hospital::factory()->create();
        $bed = $this->bed($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($this->user($h, 'receptionist'))->post('/admin/admissions', [
            'patient_id' => $patient->id, 'bed_id' => $bed->id,
        ])->assertForbidden();
    }

    public function test_summary_pdf_renders(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $bed = $this->bed($h);
        $admission = app(AdmissionService::class)->admit($patient, $bed, []);
        app(AdmissionService::class)->discharge($admission->fresh(), \App\Enums\AdmissionStatus::Discharged);

        $res = $this->actingAs($nurse)->get("/admin/admissions/{$admission->uuid}/summary");
        $res->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admissions_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $bedB = $this->bed($b);
        $patientB = Patient::factory()->create(['hospital_id' => $b->id]);
        $admissionB = app(AdmissionService::class)->admit($patientB, $bedB, []);

        $this->actingAs($this->user($a, 'nurse'))->get("/admin/admissions/{$admissionB->uuid}")->assertNotFound();
    }
}
