<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Hospital;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        // The gate is off by default in tests; this suite exercises it explicitly.
        config(['onboarding.gate_enabled' => true]);
    }

    private function admin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    private function completeOnboarding(Hospital $h): void
    {
        $h->update(['settings' => ['billing' => ['currency_code' => 'UGX']]]);
        app(CurrentHospital::class)->set($h->id);
        Service::factory()->create(['hospital_id' => $h->id]);
        Department::factory()->create(['hospital_id' => $h->id]);
        // At least one staff member beyond the founding admin created by admin().
        User::factory()->create(['hospital_id' => $h->id, 'role' => 'nurse']);
    }

    public function test_admin_with_incomplete_setup_is_redirected_to_onboarding(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->admin($h))
            ->get('/admin/patients')
            ->assertRedirect(route('admin.onboarding'));
    }

    public function test_the_dashboard_also_redirects_when_incomplete(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->admin($h))
            ->get('/admin')
            ->assertRedirect(route('admin.onboarding'));
    }

    public function test_the_onboarding_page_and_setup_pages_stay_reachable(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->admin($h);

        // No redirect loop: the checklist itself is reachable.
        $this->actingAs($admin)->get('/admin/onboarding')->assertOk();
        // Every page needed to COMPLETE onboarding must be reachable.
        $this->actingAs($admin)->get('/admin/settings/billing')->assertOk();
        $this->actingAs($admin)->get('/admin/services')->assertOk();
        $this->actingAs($admin)->get('/admin/departments')->assertOk();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_missing_staff_still_redirects(): void
    {
        // Billing + price list + department done, but no staff invited yet.
        $h = Hospital::factory()->create(['settings' => ['billing' => ['currency_code' => 'UGX']]]);
        app(CurrentHospital::class)->set($h->id);
        Service::factory()->create(['hospital_id' => $h->id]);
        Department::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($this->admin($h))
            ->get('/admin')
            ->assertRedirect(route('admin.onboarding'));
    }

    public function test_a_trial_hospital_that_finished_setup_is_not_gated(): void
    {
        // Fully set up (billing + price list + department + staff) but still on
        // a free trial with NO paid subscription — must NOT be trapped behind the
        // paywall; the gate lets them in (trial expiry is EnsureSubscribed's job).
        $h = Hospital::factory()->create(['settings' => ['billing' => ['currency_code' => 'UGX']]]);
        app(CurrentHospital::class)->set($h->id);
        Service::factory()->create(['hospital_id' => $h->id]);
        Department::factory()->create(['hospital_id' => $h->id]);
        User::factory()->create(['hospital_id' => $h->id, 'role' => 'nurse']);
        \App\Models\Subscription::factory()->for($h)->for(\App\Models\Plan::factory())->create([
            'status' => \App\Enums\SubscriptionStatus::Trialing,
        ]);

        $this->actingAs($this->admin($h))
            ->get('/admin')
            ->assertOk();
    }

    public function test_completed_setup_lifts_the_gate(): void
    {
        $h = Hospital::factory()->create();
        $this->completeOnboarding($h);

        $this->actingAs($this->admin($h))
            ->get('/admin/patients')
            ->assertOk();
    }

    public function test_skip_for_now_suppresses_the_gate_for_the_session(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->admin($h);

        $this->actingAs($admin)->get('/admin/onboarding/skip')->assertRedirect(route('admin.dashboard'));
        // Same session: no longer gated.
        $this->actingAs($admin)->get('/admin/patients')->assertOk();
    }

    public function test_non_admin_staff_are_never_gated(): void
    {
        $h = Hospital::factory()->create(); // incomplete
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        $doctor->syncSpatieRole();

        // Doctor can view patients and must NOT be funnelled through onboarding.
        $this->actingAs($doctor)->get('/admin/patients')->assertOk();
    }

    public function test_gate_can_be_disabled_by_config(): void
    {
        config(['onboarding.gate_enabled' => false]);
        $h = Hospital::factory()->create(); // incomplete

        $this->actingAs($this->admin($h))->get('/admin/patients')->assertOk();
    }
}
