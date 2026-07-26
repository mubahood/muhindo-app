<?php

namespace Tests\Feature;

use App\Enums\MedicalServiceStatus;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\MedicalService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalServiceOrderTest extends TestCase
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

    public function test_order_a_service_line_snapshots_and_totals(): void
    {
        $h = Hospital::factory()->create();
        $recep = $this->user($h, 'receptionist');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);
        $svc = Service::factory()->create(['hospital_id' => $h->id, 'name' => 'Suture', 'price' => '30.00']);

        $this->actingAs($recep)->post("/admin/consultations/{$c->uuid}/services", [
            'service_id' => $svc->id, 'quantity' => 2,
        ])->assertRedirect();

        $line = MedicalService::firstOrFail();
        $this->assertSame('30.00', (string) $line->unit_price);
        $this->assertSame('60.00', (string) $line->line_total);
    }

    public function test_cannot_order_another_hospitals_service(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $c = Consultation::factory()->create(['hospital_id' => $a->id]);
        $svcB = Service::factory()->create(['hospital_id' => $b->id]);

        $this->actingAs($this->user($a, 'receptionist'))->post("/admin/consultations/{$c->uuid}/services", [
            'service_id' => $svcB->id, 'quantity' => 1,
        ])->assertSessionHasErrors('service_id');
        $this->assertDatabaseCount('medical_services', 0);
    }

    public function test_cancel_a_line(): void
    {
        $h = Hospital::factory()->create();
        $recep = $this->user($h, 'receptionist');
        $c = Consultation::factory()->create(['hospital_id' => $h->id]);
        $svc = Service::factory()->create(['hospital_id' => $h->id, 'price' => '10.00']);
        $this->actingAs($recep)->post("/admin/consultations/{$c->uuid}/services", ['service_id' => $svc->id, 'quantity' => 1]);
        $line = MedicalService::firstOrFail();

        $this->actingAs($recep)->delete("/admin/consultations/{$c->uuid}/services/{$line->id}")->assertRedirect();
        $this->assertSame(MedicalServiceStatus::Cancelled, $line->fresh()->status);
    }
}
