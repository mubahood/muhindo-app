<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\User;
use App\Support\CurrentHospital;
use App\Support\HospitalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    public function test_admin_configures_currency_and_it_persists(): void
    {
        $h = Hospital::factory()->create(['currency' => 'USD']);
        $admin = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $admin->syncSpatieRole();

        $this->actingAs($admin)->put('/admin/settings/billing', [
            'currency_code' => 'kes', 'currency_symbol' => 'KSh', 'currency_position' => 'before',
            'decimals' => 0, 'thousands_separator' => ',', 'decimal_separator' => '.',
            'tax_enabled' => 1, 'tax_label' => 'VAT', 'tax_rate' => 16,
            'consultation_fee' => 500, 'invoice_prefix' => 'INV',
        ])->assertRedirect();

        $h->refresh();
        $this->assertSame('KES', $h->currency);
        $this->assertSame('KSh', $h->settings['billing']['currency_symbol']);

        app(CurrentHospital::class)->set($h->id);
        $this->assertSame('KSh1,500', app(HospitalSettings::class)->format('1500'));
    }

    public function test_non_admin_cannot_change_billing_settings(): void
    {
        $h = Hospital::factory()->create();
        $nurse = User::factory()->create(['hospital_id' => $h->id, 'role' => 'nurse']);
        $nurse->syncSpatieRole();

        $this->actingAs($nurse)->get('/admin/settings/billing')->assertForbidden();
    }
}
