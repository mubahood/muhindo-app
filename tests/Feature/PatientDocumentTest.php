<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        Storage::fake('local');
    }

    private function admin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_upload_download_and_delete(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->admin($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post("/admin/patients/{$patient->uuid}/documents", [
            'type' => 'insurance',
            'note' => 'Scheme card',
            'file' => UploadedFile::fake()->create('cover.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $doc = PatientDocument::where('patient_id', $patient->id)->firstOrFail();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertStringStartsWith("patients/{$h->id}/{$patient->id}/", $doc->file_path);

        $this->actingAs($user)->get("/admin/patients/{$patient->uuid}/documents/{$doc->uuid}")->assertOk();

        $this->actingAs($user)->delete("/admin/patients/{$patient->uuid}/documents/{$doc->uuid}")->assertRedirect();
        Storage::disk('local')->assertMissing($doc->file_path);
        $this->assertSoftDeleted('patient_documents', ['id' => $doc->id]);
    }

    public function test_documents_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $patientB = Patient::factory()->create(['hospital_id' => $b->id]);

        $this->actingAs($this->admin($b))->post("/admin/patients/{$patientB->uuid}/documents", [
            'type' => 'id',
            'file' => UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'),
        ]);
        $doc = PatientDocument::withoutGlobalScopes()->where('patient_id', $patientB->id)->firstOrFail();

        // A cannot download B's document (patient not resolvable in A's scope).
        $this->actingAs($this->admin($a))
            ->get("/admin/patients/{$patientB->uuid}/documents/{$doc->uuid}")
            ->assertNotFound();
    }
}
