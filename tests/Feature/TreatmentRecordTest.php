<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\TreatmentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TreatmentRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        Storage::fake('local');
    }

    private function user(Hospital $h, string $role): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => $role]);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_doctor_adds_a_record_with_photos(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->user($h, 'doctor');
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($doctor)->post("/admin/patients/{$patient->uuid}/treatments", [
            'procedure' => 'Wound suturing',
            'description' => '3 sutures, left forearm',
            'photos' => [UploadedFile::fake()->image('wound1.jpg'), UploadedFile::fake()->image('wound2.jpg')],
        ])->assertRedirect();

        $record = TreatmentRecord::firstOrFail();
        $this->assertSame(2, $record->photos()->count());
        Storage::disk('local')->assertExists($record->photos()->first()->photo_path);
        $this->assertStringStartsWith("treatments/{$h->id}/{$record->id}/", $record->photos()->first()->photo_path);
    }

    public function test_delete_removes_photos_from_disk(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->user($h, 'doctor');
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $this->actingAs($doctor)->post("/admin/patients/{$patient->uuid}/treatments", [
            'procedure' => 'X', 'photos' => [UploadedFile::fake()->image('a.jpg')],
        ]);
        $record = TreatmentRecord::firstOrFail();
        $path = $record->photos()->first()->photo_path;

        $this->actingAs($doctor)->delete("/admin/patients/{$patient->uuid}/treatments/{$record->uuid}")->assertRedirect();
        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted('treatment_records', ['id' => $record->id]);
    }

    public function test_receptionist_cannot_add_treatment_records(): void
    {
        $h = Hospital::factory()->create();
        $recep = $this->user($h, 'receptionist');
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($recep)->post("/admin/patients/{$patient->uuid}/treatments", ['procedure' => 'X'])->assertForbidden();
    }

    public function test_treatment_records_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $doctorB = $this->user($b, 'doctor');
        $patientB = Patient::factory()->create(['hospital_id' => $b->id]);
        $this->actingAs($doctorB)->post("/admin/patients/{$patientB->uuid}/treatments", ['procedure' => 'Secret op']);
        $recordB = TreatmentRecord::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->user($a, 'doctor'))->get("/admin/patients/{$patientB->uuid}/treatments/{$recordB->uuid}")->assertNotFound();
    }
}
