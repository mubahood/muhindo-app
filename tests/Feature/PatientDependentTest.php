<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientDependent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientDependentTest extends TestCase
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

    public function test_link_and_unlink_a_dependent(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->admin($h);
        $guardian = Patient::factory()->create(['hospital_id' => $h->id]);
        $child = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post("/admin/patients/{$guardian->uuid}/dependents", [
            'dependent_uuid' => $child->uuid, 'relationship' => 'child',
        ])->assertRedirect();

        $link = PatientDependent::where('patient_id', $guardian->id)->firstOrFail();
        $this->assertSame($child->id, $link->dependent_patient_id);

        $this->actingAs($user)->delete("/admin/patients/{$guardian->uuid}/dependents/{$link->id}")->assertRedirect();
        $this->assertDatabaseMissing('patient_dependents', ['id' => $link->id]);
    }

    public function test_cannot_link_a_patient_to_itself(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->admin($h);
        $p = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post("/admin/patients/{$p->uuid}/dependents", [
            'dependent_uuid' => $p->uuid, 'relationship' => 'child',
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('patient_dependents', 0);
    }

    public function test_cannot_link_a_dependent_from_another_hospital(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $guardianA = Patient::factory()->create(['hospital_id' => $a->id]);
        $childB = Patient::factory()->create(['hospital_id' => $b->id]);

        // B's patient uuid is not resolvable in A's scope → validation fails (exists rule scoped).
        $this->actingAs($this->admin($a))->post("/admin/patients/{$guardianA->uuid}/dependents", [
            'dependent_uuid' => $childB->uuid, 'relationship' => 'child',
        ])->assertSessionHasErrors('dependent_uuid');

        $this->assertDatabaseCount('patient_dependents', 0);
    }
}
