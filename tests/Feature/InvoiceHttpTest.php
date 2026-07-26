<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\BillingService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceHttpTest extends TestCase
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

    private function chargedConsultation(Hospital $h): Consultation
    {
        app(CurrentHospital::class)->set($h->id);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $c = Consultation::factory()->create(['hospital_id' => $h->id, 'patient_id' => $patient->id]);
        $svc = Service::factory()->create(['hospital_id' => $h->id, 'price' => '100.00']);
        app(BillingService::class)->addServiceLine($c, $svc, 1);

        return $c->fresh();
    }

    public function test_generate_invoice_then_pay_cash(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->user($h, 'receptionist');
        $c = $this->chargedConsultation($h);

        $this->actingAs($user)->post("/admin/consultations/{$c->uuid}/invoice", ['discount' => '0'])->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->assertSame('100.00', (string) $invoice->total);

        $this->actingAs($user)->post("/admin/invoices/{$invoice->uuid}/payments", [
            'method' => 'cash', 'amount' => '100.00',
        ])->assertRedirect();
        $this->assertSame('0.00', (string) $invoice->fresh()->balance);
        $this->assertSame('paid', $invoice->fresh()->status->value);
    }

    public function test_invoice_pdf_renders(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->user($h, 'accountant');
        $c = $this->chargedConsultation($h);
        $invoice = app(BillingService::class)->generateInvoice($c, '0.00', null);

        $res = $this->actingAs($user)->get("/admin/invoices/{$invoice->uuid}/pdf");
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_role_without_billing_manage_cannot_take_payment(): void
    {
        $h = Hospital::factory()->create();
        $nurse = $this->user($h, 'nurse');
        $c = $this->chargedConsultation($h);
        $invoice = app(BillingService::class)->generateInvoice($c, '0.00', null);

        $this->actingAs($nurse)->post("/admin/invoices/{$invoice->uuid}/payments", [
            'method' => 'cash', 'amount' => '10.00',
        ])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_invoices_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $cB = $this->chargedConsultation($b);
        $invoiceB = app(BillingService::class)->generateInvoice($cB, '0.00', null);

        $adminA = $this->user($a, 'hospital_admin');
        $this->actingAs($adminA)->get("/admin/invoices/{$invoiceB->uuid}")->assertNotFound();
        $this->actingAs($adminA)->post("/admin/invoices/{$invoiceB->uuid}/payments", [
            'method' => 'cash', 'amount' => '10.00',
        ])->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }
}
