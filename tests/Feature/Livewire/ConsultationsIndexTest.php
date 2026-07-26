<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Consultations\Index;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsultationsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function actingDoctor(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    private function actingReceptionist(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'receptionist']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    public function test_it_lists_and_searches_consultations(): void
    {
        $h = Hospital::factory()->create();
        $doctor = $this->actingDoctor($h);
        $p1 = Patient::factory()->create(['hospital_id' => $h->id, 'first_name' => 'Findable']);
        $p2 = Patient::factory()->create(['hospital_id' => $h->id, 'first_name' => 'Hiddenone']);
        Consultation::factory()->create(['hospital_id' => $h->id, 'patient_id' => $p1->id, 'doctor_user_id' => $doctor->id]);
        Consultation::factory()->create(['hospital_id' => $h->id, 'patient_id' => $p2->id, 'doctor_user_id' => $doctor->id]);

        Livewire::test(Index::class)
            ->assertSee('Findable')
            ->set('search', 'Findable')
            ->assertSee('Findable')
            ->assertDontSee('Hiddenone');
    }

    public function test_a_role_without_consultation_view_is_forbidden(): void
    {
        $h = Hospital::factory()->create();
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        $u->syncRoles([]);
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_modal_opens_an_encounter_via_service(): void
    {
        $h = Hospital::factory()->create();
        $this->actingReceptionist($h);
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        Livewire::test(Index::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('patient_id', $patient->id)
            ->set('doctor_user_id', $doctor->id)
            ->set('reason', 'Fever')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('consultations', ['patient_id' => $patient->id]);
    }

    public function test_modal_requires_a_patient(): void
    {
        $h = Hospital::factory()->create();
        $this->actingReceptionist($h);

        Livewire::test(Index::class)
            ->call('create')
            ->call('save')
            ->assertHasErrors('patient_id');
    }
}
