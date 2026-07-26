<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Patient;
use App\Models\User;
use App\Services\LabService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabHttpTest extends TestCase
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

    private function scenario(Hospital $h): array
    {
        app(CurrentHospital::class)->set($h->id);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $c = Consultation::factory()->create(['hospital_id' => $h->id, 'patient_id' => $patient->id]);
        $test = LabTest::factory()->create(['hospital_id' => $h->id, 'price' => '15.00']);

        return [$c, $test];
    }

    public function test_doctor_orders_labs_then_tech_results_and_completes(): void
    {
        $h = Hospital::factory()->create();
        [$c, $test] = $this->scenario($h);
        $doctor = $this->user($h, 'doctor');
        $tech = $this->user($h, 'lab_technician');

        $this->actingAs($doctor)->post("/admin/consultations/{$c->uuid}/lab-orders", [
            'test_ids' => [$test->id], 'clinical_notes' => 'rule out malaria',
        ])->assertRedirect();

        $order = LabOrder::firstOrFail();
        $this->assertSame(1, $c->medicalServices()->count()); // billed

        $item = $order->items()->first();
        $this->actingAs($tech)->post("/admin/lab-order-items/{$item->id}/result", [
            'result_value' => 'Positive', 'result_flag' => 'abnormal',
        ])->assertRedirect();
        $this->assertSame('Positive', $item->fresh()->result_value);

        // Advance through the machine.
        foreach (['collected', 'processing', 'completed'] as $s) {
            $this->actingAs($tech)->post("/admin/lab-orders/{$order->uuid}/transition", ['status' => $s])->assertRedirect();
        }
        $this->assertSame('completed', $order->fresh()->status->value);
    }

    public function test_pdf_renders(): void
    {
        $h = Hospital::factory()->create();
        [$c, $test] = $this->scenario($h);
        $order = app(LabService::class)->order($c, [$test->id], null, null);

        $res = $this->actingAs($this->user($h, 'lab_technician'))->get("/admin/lab-orders/{$order->uuid}/pdf");
        $res->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_receptionist_cannot_order_labs(): void
    {
        $h = Hospital::factory()->create();
        [$c, $test] = $this->scenario($h);

        $this->actingAs($this->user($h, 'receptionist'))->post("/admin/consultations/{$c->uuid}/lab-orders", [
            'test_ids' => [$test->id],
        ])->assertForbidden();
        $this->assertDatabaseCount('lab_orders', 0);
    }

    public function test_lab_orders_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        [$cB, $testB] = $this->scenario($b);
        $orderB = app(LabService::class)->order($cB, [$testB->id], null, null);

        $techA = $this->user($a, 'lab_technician');
        $this->actingAs($techA)->get("/admin/lab-orders/{$orderB->uuid}")->assertNotFound();
    }
}
