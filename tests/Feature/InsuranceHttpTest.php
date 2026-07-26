<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\InsuranceClaim;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function user(Hospital $h, string $role): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => $role]);
        $u->syncSpatieRole();

        return $u;
    }

    private function invoice(Hospital $h)
    {
        app(CurrentHospital::class)->set($h->id);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $c = Consultation::factory()->create(['hospital_id' => $h->id, 'patient_id' => $patient->id]);
        $svc = Service::factory()->create(['hospital_id' => $h->id, 'price' => '100.00']);
        app(BillingService::class)->addServiceLine($c->fresh(), $svc, 1);

        return [app(BillingService::class)->generateInvoice($c->fresh(), '0.00', null), $patient];
    }

    public function test_accountant_creates_a_claim_and_marks_it_paid(): void
    {
        $h = Hospital::factory()->create(['currency' => 'UGX']);
        $acct = $this->user($h, 'accountant');
        [$invoice, $patient] = $this->invoice($h);
        $provider = InsuranceProvider::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($acct)->post('/admin/insurance-claims', [
            'patient_id' => $patient->id, 'insurance_provider_id' => $provider->id, 'invoice_id' => $invoice->id, 'amount' => '100.00',
        ])->assertRedirect();
        $claim = InsuranceClaim::firstOrFail();

        foreach (['submitted', 'approved', 'paid'] as $s) {
            $this->actingAs($acct)->post("/admin/insurance-claims/{$claim->uuid}/transition", ['status' => $s])->assertRedirect();
        }

        $this->assertSame('paid', $claim->fresh()->status->value);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_receptionist_can_view_but_not_create_claims(): void
    {
        $h = Hospital::factory()->create();
        $recep = $this->user($h, 'receptionist');

        $this->actingAs($recep)->get('/admin/insurance-claims')->assertOk();
        $this->actingAs($recep)->get('/admin/insurance-claims/create')->assertForbidden();
    }

    public function test_doctor_has_no_insurance_access(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->user($h, 'doctor'))->get('/admin/insurance-claims')->assertForbidden();
    }

    public function test_claims_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create(['currency' => 'UGX']);
        [$invoiceB, $patientB] = $this->invoice($b);
        $providerB = InsuranceProvider::factory()->create(['hospital_id' => $b->id]);
        $claimB = app(\App\Services\InsuranceService::class)->createClaim([
            'patient_id' => $patientB->id, 'insurance_provider_id' => $providerB->id, 'invoice_id' => $invoiceB->id, 'amount' => '50.00',
        ]);

        $this->actingAs($this->user($a, 'accountant'))->get("/admin/insurance-claims/{$claimB->uuid}")->assertNotFound();
    }
}
