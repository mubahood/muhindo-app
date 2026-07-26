<?php

namespace Tests\Feature\Livewire;

use App\Models\Bed;
use App\Models\Hospital;
use App\Models\User;
use App\Models\Ward;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InpatientIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function actingAdmin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    private function stripped(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncRoles([]);
        $this->actingAs($u);
        app(CurrentHospital::class)->set($h->id);

        return $u;
    }

    public function test_wards_index_searches_and_deletes(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        Ward::factory()->create(['hospital_id' => $h->id, 'name' => 'Maternity Wing']);
        $drop = Ward::factory()->create(['hospital_id' => $h->id, 'name' => 'Surgical Wing']);

        Livewire::test(\App\Livewire\Wards\Index::class)
            ->assertSee('Maternity Wing')
            ->set('search', 'Maternity')
            ->assertSee('Maternity Wing')
            ->assertDontSee('Surgical Wing')
            ->set('search', '')
            ->call('delete', $drop->id)
            ->assertHasNoErrors();

        $this->assertNull(Ward::find($drop->id));
    }

    public function test_beds_index_searches(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        $ward = Ward::factory()->create(['hospital_id' => $h->id]);
        Bed::factory()->create(['hospital_id' => $h->id, 'ward_id' => $ward->id, 'name' => 'BedAlpha']);
        Bed::factory()->create(['hospital_id' => $h->id, 'ward_id' => $ward->id, 'name' => 'BedBravo']);

        Livewire::test(\App\Livewire\Beds\Index::class)
            ->set('search', 'BedAlpha')
            ->assertSee('BedAlpha')
            ->assertDontSee('BedBravo');
    }

    public function test_admissions_index_renders_and_gates(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAdmin($h);
        Livewire::test(\App\Livewire\Admissions\Index::class)->assertOk()->assertSee('Admissions');
    }

    public function test_inpatient_indexes_forbidden_without_ipd_view(): void
    {
        $h = Hospital::factory()->create();
        $this->stripped($h);
        Livewire::test(\App\Livewire\Wards\Index::class)->assertForbidden();
        Livewire::test(\App\Livewire\Beds\Index::class)->assertForbidden();
        Livewire::test(\App\Livewire\Admissions\Index::class)->assertForbidden();
    }
}
