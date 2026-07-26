<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockHttpTest extends TestCase
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

    public function test_pharmacist_creates_and_receives_stock(): void
    {
        $h = Hospital::factory()->create();
        $pharm = $this->user($h, 'pharmacist');

        $this->actingAs($pharm)->post('/admin/stock', [
            'name' => 'Paracetamol', 'unit' => 'tablets', 'cost_price' => '0.10',
            'sale_price' => '0.25', 'reorder_level' => '100', 'is_active' => 1,
        ])->assertRedirect('/admin/stock');

        $item = StockItem::firstOrFail();
        $this->actingAs($pharm)->post("/admin/stock/{$item->uuid}/receive", ['quantity' => '500', 'unit_cost' => '0.10'])
            ->assertRedirect();

        $this->assertSame('500.00', (string) $item->fresh()->current_quantity);
        $this->assertSame('50.00', (string) $item->fresh()->current_stock_value); // 500 × 0.10
    }

    public function test_receiving_over_dispensing_cannot_go_negative_via_adjust(): void
    {
        $h = Hospital::factory()->create();
        $pharm = $this->user($h, 'pharmacist');
        app(CurrentHospital::class)->set($h->id);
        $item = StockItem::factory()->create(['hospital_id' => $h->id]);
        app(StockService::class)->receive($item, '5', '1.00');

        $this->actingAs($pharm)->post("/admin/stock/{$item->uuid}/adjust", ['reason' => 'wastage', 'quantity' => '9'])
            ->assertSessionHas('error');
        $this->assertSame('5.00', (string) $item->fresh()->current_quantity);
    }

    public function test_item_with_movements_cannot_be_deleted(): void
    {
        $h = Hospital::factory()->create();
        $pharm = $this->user($h, 'pharmacist');
        app(CurrentHospital::class)->set($h->id);
        $item = StockItem::factory()->create(['hospital_id' => $h->id]);
        app(StockService::class)->receive($item, '10', '1.00');

        $this->actingAs($pharm)->delete("/admin/stock/{$item->uuid}")->assertSessionHas('error');
        $this->assertNotNull(StockItem::find($item->id)); // still there
    }

    public function test_role_without_pharmacy_manage_cannot_create(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->user($h, 'nurse'))->post('/admin/stock', [
            'name' => 'X', 'unit' => 'u', 'cost_price' => '1', 'sale_price' => '1', 'reorder_level' => '1',
        ])->assertForbidden();
    }

    public function test_stock_is_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $itemB = StockItem::factory()->create(['hospital_id' => $b->id, 'name' => 'SecretDrug']);

        $adminA = $this->user($a, 'hospital_admin');
        $this->actingAs($adminA)->get('/admin/stock')->assertDontSee('SecretDrug');
        $this->actingAs($adminA)->get("/admin/stock/{$itemB->uuid}")->assertNotFound();
        $this->actingAs($adminA)->post("/admin/stock/{$itemB->uuid}/receive", ['quantity' => '10'])->assertNotFound();
    }

    public function test_alerts_board_lists_low_and_expiring(): void
    {
        $h = Hospital::factory()->create();
        $admin = $this->user($h, 'hospital_admin');
        StockItem::factory()->create(['hospital_id' => $h->id, 'name' => 'LowOne', 'current_quantity' => '2', 'reorder_level' => '10']);
        StockItem::factory()->create(['hospital_id' => $h->id, 'name' => 'ExpiringOne', 'current_quantity' => '50', 'reorder_level' => '1', 'expiry_date' => now()->addDays(30)->toDateString()]);

        $res = $this->actingAs($admin)->get('/admin/stock/alerts');
        $res->assertOk()->assertSee('LowOne')->assertSee('ExpiringOne');
    }
}
