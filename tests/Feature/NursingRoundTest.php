<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
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

class NursingRoundTest extends TestCase
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

    private function admission(Hospital $h): Admission
    {
        app(CurrentHospital::class)->set($h->id);
        $ward = Ward::factory()->create(['hospital_id' => $h->id]);
        $bed = Bed::factory()->create(['hospital_id' => $h->id, 'ward_id' => $ward->id]);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        return app(AdmissionService::class)->admit($patient, $bed, []);
    }

    public function test_nurse_records_note_vitals_and_medication(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $adm = $this->admission($h);

        $this->actingAs($nurse)->post("/admin/admissions/{$adm->uuid}/nursing-notes", ['note' => 'Resting comfortably'])->assertRedirect();
        $this->actingAs($nurse)->post("/admin/admissions/{$adm->uuid}/vital-rounds", ['temperature' => '37.1', 'blood_pressure' => '118/76'])->assertRedirect();
        $this->actingAs($nurse)->post("/admin/admissions/{$adm->uuid}/medications", ['drug_name' => 'Paracetamol', 'dose' => '1g', 'route' => 'IV', 'status' => 'given'])->assertRedirect();

        $this->assertSame(1, $adm->nursingNotes()->count());
        $this->assertSame(1, $adm->vitalRounds()->count());
        $this->assertSame(1, $adm->medications()->count());
    }

    public function test_closed_admission_rejects_new_entries(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $adm = $this->admission($h);
        app(AdmissionService::class)->discharge($adm->fresh(), AdmissionStatus::Discharged);

        $this->actingAs($nurse)->post("/admin/admissions/{$adm->uuid}/nursing-notes", ['note' => 'late'])->assertStatus(422);
        $this->assertSame(0, $adm->nursingNotes()->count());
    }

    public function test_receptionist_cannot_record_nursing_entries(): void
    {
        $h = Hospital::factory()->create();
        $adm = $this->admission($h);

        $this->actingAs($this->user($h, 'receptionist'))->post("/admin/admissions/{$adm->uuid}/nursing-notes", ['note' => 'x'])->assertForbidden();
    }

    public function test_nursing_entries_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $admB = $this->admission($b);

        $this->actingAs($this->user($a, 'nurse'))->post("/admin/admissions/{$admB->uuid}/nursing-notes", ['note' => 'x'])->assertNotFound();
    }
}
