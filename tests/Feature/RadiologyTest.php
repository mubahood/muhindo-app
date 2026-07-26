<?php

namespace Tests\Feature;

use App\Enums\RadiologyOrderStatus;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\RadiologyOrder;
use App\Models\RadiologyStudy;
use App\Models\User;
use App\Services\RadiologyService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiologyTest extends TestCase
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
        $study = RadiologyStudy::factory()->create(['hospital_id' => $h->id, 'price' => '40.00']);

        return [$c, $study];
    }

    public function test_order_snapshots_and_bills(): void
    {
        $h = Hospital::factory()->create();
        [$c, $study] = $this->scenario($h);

        $order = app(RadiologyService::class)->order($c, [$study->id], 'trauma', null);

        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, $c->medicalServices()->count());
        $this->assertSame('40.00', (string) $c->medicalServices()->first()->line_total);
    }

    public function test_doctor_orders_radiologist_reports_and_completes(): void
    {
        $h = Hospital::factory()->create();
        [$c, $study] = $this->scenario($h);
        $doctor = $this->user($h, 'doctor');
        $rad = $this->user($h, 'radiologist');

        $this->actingAs($doctor)->post("/admin/consultations/{$c->uuid}/radiology-orders", [
            'study_ids' => [$study->id],
        ])->assertRedirect();
        $order = RadiologyOrder::firstOrFail();

        $this->actingAs($rad)->post("/admin/radiology-orders/{$order->uuid}/report", [
            'findings' => 'No fracture seen.', 'impression' => 'Normal chest.',
        ])->assertRedirect();
        $this->assertSame('No fracture seen.', $order->fresh()->findings);

        foreach (['scheduled', 'performed', 'reported'] as $s) {
            $this->actingAs($rad)->post("/admin/radiology-orders/{$order->uuid}/transition", ['status' => $s])->assertRedirect();
        }
        $this->assertSame('reported', $order->fresh()->status->value);
        $this->assertNotNull($order->fresh()->reported_at);
    }

    public function test_skip_ahead_status_is_rejected(): void
    {
        $h = Hospital::factory()->create();
        [$c, $study] = $this->scenario($h);
        $order = app(RadiologyService::class)->order($c, [$study->id], null, null);

        $this->expectException(\RuntimeException::class);
        app(RadiologyService::class)->transition($order, RadiologyOrderStatus::Reported);
    }

    public function test_pdf_renders_and_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        [$cB, $studyB] = $this->scenario($b);
        $orderB = app(RadiologyService::class)->order($cB, [$studyB->id], null, null);

        // Owner can render.
        $this->actingAs($this->user($b, 'radiologist'))->get("/admin/radiology-orders/{$orderB->uuid}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Other hospital can't see it.
        $this->actingAs($this->user($a, 'radiologist'))->get("/admin/radiology-orders/{$orderB->uuid}")->assertNotFound();
    }
}
