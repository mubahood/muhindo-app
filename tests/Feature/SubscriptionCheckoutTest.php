<?php

namespace Tests\Feature;

use App\Models\GatewayLog;
use App\Models\Hospital;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Gateway\GatewayPaymentService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        config()->set('services.flutterwave', [
            'secret_key' => 'FLWSECK-test', 'public_key' => 'pk', 'encryption_key' => 'ek',
            'secret_hash' => 'my-hash', 'base_url' => 'https://api.flutterwave.com',
            'currency' => 'USD', 'payment_options' => 'card', 'timeout' => 20,
        ]);
    }

    private function owner(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_checkout_initializes_a_subscription_gateway_log_and_redirects(): void
    {
        $h = Hospital::factory()->create();
        $plan = Plan::factory()->create(['price' => '149.00', 'is_active' => true]);
        Http::fake(['*/v3/payments' => Http::response(['status' => 'success', 'data' => ['link' => 'https://checkout.flutterwave.com/sub/1']], 200)]);

        $this->actingAs($this->owner($h))->post("/admin/subscription/{$plan->id}/checkout")
            ->assertRedirect('https://checkout.flutterwave.com/sub/1');

        $log = GatewayLog::firstOrFail();
        $this->assertStringStartsWith('SUB-', $log->tx_ref);
        $this->assertSame('subscription', $log->meta['purpose']);
        $this->assertSame($plan->id, $log->meta['plan_id']);
    }

    public function test_settle_activates_the_subscription_and_records_a_payment(): void
    {
        $h = Hospital::factory()->create();
        $plan = Plan::factory()->create(['price' => '149.00', 'billing_cycle' => 'monthly', 'is_active' => true]);
        app(CurrentHospital::class)->set($h->id);
        // A pre-existing trial to convert.
        Subscription::factory()->create(['hospital_id' => $h->id, 'plan_id' => $plan->id, 'status' => 'trialing']);

        $log = GatewayLog::create([
            'hospital_id' => $h->id, 'provider' => 'flutterwave', 'tx_ref' => 'SUB-xyz',
            'status' => 'pending', 'amount' => '149.00', 'currency' => 'USD',
            'meta' => ['purpose' => 'subscription', 'plan_id' => $plan->id],
        ]);
        Http::fake(['*/v3/transactions/*/verify' => Http::response([
            'status' => 'success',
            'data' => ['status' => 'successful', 'tx_ref' => 'SUB-xyz', 'id' => 900, 'amount' => 149, 'currency' => 'USD', 'payment_type' => 'card'],
        ], 200)]);

        $result = app(GatewayPaymentService::class)->settle('900');
        $this->assertSame('settled', $result);

        $sub = Subscription::where('hospital_id', $h->id)->latest('starts_at')->first();
        $this->assertSame('active', $sub->status->value);
        $this->assertNull($sub->trial_ends_at);
        $this->assertTrue($sub->ends_at->isFuture());
        $this->assertSame(1, $sub->payments()->count());
        $this->assertSame('flutterwave', $sub->payments()->first()->method);
        $this->assertSame('successful', $log->fresh()->status);
    }
}
