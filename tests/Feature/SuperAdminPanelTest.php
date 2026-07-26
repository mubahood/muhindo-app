<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Hospital;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HMS_PLAN.md §7 Phase 0 Step 5 — super-admin panel (hospitals, plans,
 * subscriptions, record payments). Route convention: /super/* is SaaS
 * central, gated to Super Admin only (§10).
 */
class SuperAdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create([
            'hospital_id' => null,
            'role' => 'super_admin',
            'is_admin' => true,
        ]);
    }

    private function hospitalAdmin(Hospital $hospital): User
    {
        return User::factory()->create([
            'hospital_id' => $hospital->id,
            'role' => 'hospital_admin',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/super/hospitals')->assertRedirect(route('admin.login'));
    }

    public function test_a_hospital_scoped_admin_is_forbidden(): void
    {
        $hospital = Hospital::factory()->create();
        $admin = $this->hospitalAdmin($hospital);

        $this->actingAs($admin)->get('/super/hospitals')->assertForbidden();
        $this->actingAs($admin)->get('/super/plans')->assertForbidden();
        $this->actingAs($admin)->get('/super/subscriptions')->assertForbidden();
    }

    public function test_super_admin_can_reach_every_panel_index(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get('/super/hospitals')->assertOk();
        $this->actingAs($admin)->get('/super/plans')->assertOk();
        $this->actingAs($admin)->get('/super/subscriptions')->assertOk();
    }

    public function test_super_admin_can_create_a_hospital(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/super/hospitals', [
            'name' => 'Nakasero Hospital',
            'timezone' => 'Africa/Kampala',
            'currency' => 'UGX',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('super.hospitals.index'));
        $this->assertDatabaseHas('hospitals', ['name' => 'Nakasero Hospital', 'slug' => 'nakasero-hospital']);
    }

    public function test_super_admin_can_create_a_plan_with_limits(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/super/plans', [
            'name' => 'Growth',
            'price' => 400000,
            'billing_cycle' => 'monthly',
            'max_users' => 25,
            'max_patients' => 5000,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('super.plans.index'));
        $plan = Plan::where('name', 'Growth')->firstOrFail();
        $this->assertSame(25, $plan->limit('max_users'));
        $this->assertSame(5000, $plan->limit('max_patients'));
    }

    public function test_super_admin_can_create_a_subscription(): void
    {
        $admin = $this->superAdmin();
        $hospital = Hospital::factory()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)->post('/super/subscriptions', [
            'hospital_id' => $hospital->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'starts_at' => now()->toDateString(),
        ]);

        $response->assertRedirect(route('super.subscriptions.index'));
        $this->assertDatabaseHas('subscriptions', [
            'hospital_id' => $hospital->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
        ]);
    }

    public function test_recording_a_payment_extends_and_reactivates_a_lapsed_subscription(): void
    {
        $admin = $this->superAdmin();
        $subscription = Subscription::factory()->expired()->create();

        $response = $this->actingAs($admin)->post(
            route('super.subscriptions.payments.store', $subscription),
            [
                'amount' => 150000,
                'method' => 'manual',
                'paid_at' => now()->toDateString(),
                'extend_days' => 30,
            ]
        );

        $response->assertRedirect(route('super.subscriptions.edit', $subscription));

        $subscription->refresh();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $subscription->id,
            'amount' => '150000.00',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_recording_a_payment_extends_from_the_current_end_date_when_still_active(): void
    {
        $admin = $this->superAdmin();
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'ends_at' => now()->addDays(10),
        ]);
        $expectedBase = $subscription->ends_at->copy();

        $this->actingAs($admin)->post(route('super.subscriptions.payments.store', $subscription), [
            'amount' => 150000,
            'method' => 'manual',
            'paid_at' => now()->toDateString(),
            'extend_days' => 30,
        ]);

        $subscription->refresh();
        $this->assertEqualsWithDelta(
            $expectedBase->addDays(30)->timestamp,
            $subscription->ends_at->timestamp,
            5,
        );
    }
}
