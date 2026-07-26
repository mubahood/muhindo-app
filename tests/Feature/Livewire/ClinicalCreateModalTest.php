<?php

namespace Tests\Feature\Livewire;

use App\Enums\BedStatus;
use App\Models\Bed;
use App\Models\Hospital;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClinicalCreateModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function actingAccountant(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'accountant']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    private function actingAdmin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    public function test_insurance_claim_modal_creates_via_service(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAccountant($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $provider = InsuranceProvider::factory()->create(['hospital_id' => $h->id, 'is_active' => true]);

        Livewire::test(\App\Livewire\InsuranceClaims\Index::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('patient_id', $patient->id)
            ->set('insurance_provider_id', $provider->id)
            ->set('amount', '150000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('insurance_claims', [
            'patient_id' => $patient->id,
            'insurance_provider_id' => $provider->id,
        ]);
    }

    public function test_insurance_claim_modal_requires_patient_provider_amount(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAccountant($h);

        Livewire::test(\App\Livewire\InsuranceClaims\Index::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['patient_id', 'insurance_provider_id', 'amount']);
    }

    public function test_admission_modal_admits_to_available_bed(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $ward = Ward::factory()->create(['hospital_id' => $h->id]);
        $bed = Bed::factory()->create(['hospital_id' => $h->id, 'ward_id' => $ward->id, 'status' => BedStatus::Available->value, 'is_active' => true]);

        Livewire::test(\App\Livewire\Admissions\Index::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('patient_id', $patient->id)
            ->set('bed_id', $bed->id)
            ->set('reason', 'Observation')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('admissions', ['patient_id' => $patient->id, 'bed_id' => $bed->id]);
        $this->assertSame(BedStatus::Occupied, $bed->fresh()->status);
    }

    public function test_admission_modal_requires_patient_and_bed(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);

        Livewire::test(\App\Livewire\Admissions\Index::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors(['patient_id', 'bed_id']);
    }
}
