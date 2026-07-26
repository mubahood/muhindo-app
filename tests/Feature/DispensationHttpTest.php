<?php

namespace Tests\Feature;

use App\Models\Consultation;
use App\Models\Dispensation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensationHttpTest extends TestCase
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
        $item = StockItem::factory()->create(['hospital_id' => $h->id, 'sale_price' => '2.00']);
        app(StockService::class)->receive($item, '50', '1.00');

        return [$c, $item->fresh()];
    }

    public function test_pharmacist_dispenses_deducts_stock_and_bills(): void
    {
        $h = Hospital::factory()->create();
        [$c, $item] = $this->scenario($h);
        $pharm = $this->user($h, 'pharmacist');

        $this->actingAs($pharm)->post("/admin/consultations/{$c->uuid}/dispense", [
            'items' => [['stock_item_id' => $item->id, 'quantity' => '10']],
        ])->assertRedirect();

        $this->assertSame('40.00', (string) $item->fresh()->current_quantity); // 50 - 10
        $this->assertSame(1, Dispensation::count());
        $this->assertSame(1, $c->medicalServices()->count());
        $this->assertSame('20.00', (string) $c->medicalServices()->first()->line_total); // 10 × 2.00
    }

    public function test_role_without_dispense_permission_is_forbidden(): void
    {
        $h = Hospital::factory()->create();
        [$c, $item] = $this->scenario($h);
        $recep = $this->user($h, 'receptionist');

        $this->actingAs($recep)->post("/admin/consultations/{$c->uuid}/dispense", [
            'items' => [['stock_item_id' => $item->id, 'quantity' => '5']],
        ])->assertForbidden();
        $this->assertDatabaseCount('dispensations', 0);
    }

    public function test_cannot_dispense_another_hospitals_stock(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        [$cA] = $this->scenario($a);
        app(CurrentHospital::class)->set($b->id);
        $itemB = StockItem::factory()->create(['hospital_id' => $b->id]);

        $this->actingAs($this->user($a, 'pharmacist'))->post("/admin/consultations/{$cA->uuid}/dispense", [
            'items' => [['stock_item_id' => $itemB->id, 'quantity' => '1']],
        ])->assertSessionHasErrors('items.0.stock_item_id');
        $this->assertDatabaseCount('dispensations', 0);
    }
}
