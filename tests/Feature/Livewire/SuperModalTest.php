<?php

namespace Tests\Feature\Livewire;

use App\Models\Hospital;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function actingSuper(): User
    {
        $u = User::factory()->create(['hospital_id' => null, 'role' => 'super_admin', 'is_admin' => true]);
        $u->syncSpatieRole();
        $this->actingAs($u);

        return $u;
    }

    public function test_hospital_modal_creates(): void
    {
        $this->actingSuper();

        Livewire::test(\App\Livewire\Super\Hospitals\Index::class)
            ->call('create')
            ->assertSet('showForm', true)
            ->set('name', 'Riverside Medical')
            ->set('slug', 'riverside-medical')
            ->set('timezone', 'Africa/Kampala')
            ->set('currency', 'UGX')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('hospitals', ['slug' => 'riverside-medical']);
    }

    public function test_hospital_modal_edits(): void
    {
        $this->actingSuper();
        $h = Hospital::factory()->create(['name' => 'Old Name']);

        Livewire::test(\App\Livewire\Super\Hospitals\Index::class)
            ->call('edit', $h->id)
            ->assertSet('name', 'Old Name')
            ->set('name', 'New Name')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('New Name', $h->fresh()->name);
    }

    public function test_plan_modal_creates_with_limits(): void
    {
        $this->actingSuper();

        Livewire::test(\App\Livewire\Super\Plans\Index::class)
            ->call('create')
            ->set('name', 'Growth')
            ->set('price', '250000')
            ->set('billing_cycle', 'monthly')
            ->set('max_users', 25)
            ->set('max_patients', 5000)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $plan = Plan::where('name', 'Growth')->first();
        $this->assertNotNull($plan);
        $this->assertSame(25, $plan->limit('max_users'));
        $this->assertSame(5000, $plan->limit('max_patients'));
    }

    public function test_subscription_modal_creates(): void
    {
        $this->actingSuper();
        $h = Hospital::factory()->create();
        $plan = Plan::factory()->create(['is_active' => true]);

        Livewire::test(\App\Livewire\Super\Subscriptions\Index::class)
            ->call('create')
            ->set('hospital_id', $h->id)
            ->set('plan_id', $plan->id)
            ->set('status', 'active')
            ->set('starts_at', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $this->assertDatabaseHas('subscriptions', ['hospital_id' => $h->id, 'plan_id' => $plan->id]);
    }

    public function test_super_modals_forbidden_for_non_super(): void
    {
        $h = Hospital::factory()->create();
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();
        $this->actingAs($u);

        Livewire::test(\App\Livewire\Super\Hospitals\Index::class)->assertForbidden();
        Livewire::test(\App\Livewire\Super\Plans\Index::class)->assertForbidden();
        Livewire::test(\App\Livewire\Super\Subscriptions\Index::class)->assertForbidden();
    }
}
