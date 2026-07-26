<?php

namespace Tests\Feature;

use App\Models\FinancialYear;
use App\Models\Hospital;
use App\Models\User;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialYearHttpTest extends TestCase
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

    public function test_accountant_creates_closes_and_reopens_a_period(): void
    {
        $h = Hospital::factory()->create();
        $acct = $this->user($h, 'accountant');

        $this->actingAs($acct)->post('/admin/financial-years', ['name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'])->assertRedirect();
        $fy = FinancialYear::firstOrFail();

        $this->actingAs($acct)->post("/admin/financial-years/{$fy->id}/close")->assertRedirect();
        $this->assertSame('closed', $fy->fresh()->status->value);
        $this->actingAs($acct)->post("/admin/financial-years/{$fy->id}/reopen")->assertRedirect();
        $this->assertSame('open', $fy->fresh()->status->value);
    }

    public function test_report_page_renders(): void
    {
        $h = Hospital::factory()->create();
        app(CurrentHospital::class)->set($h->id);
        $fy = FinancialYear::create(['hospital_id' => $h->id, 'name' => 'FY x', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);

        $this->actingAs($this->user($h, 'accountant'))->get("/admin/financial-years/{$fy->id}")->assertOk()->assertSee('Payments received');
    }

    public function test_doctor_cannot_access_finance(): void
    {
        $h = Hospital::factory()->create();
        $this->actingAs($this->user($h, 'doctor'))->get('/admin/financial-years')->assertForbidden();
    }

    public function test_periods_are_tenant_isolated(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        app(CurrentHospital::class)->set($b->id);
        $fyB = FinancialYear::create(['hospital_id' => $b->id, 'name' => 'FY B', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'status' => 'open']);

        $this->actingAs($this->user($a, 'accountant'))->get("/admin/financial-years/{$fyB->id}")->assertNotFound();
    }
}
