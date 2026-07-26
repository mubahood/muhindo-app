<?php

namespace Tests\Feature;

use App\Models\Hospital;
use App\Models\Patient;
use App\Models\PatientCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The HTTP path through PatientCardController: issue → credit → debit, plus the
 * overdraft rejection surfacing as a flash error (money math itself is covered
 * unit-style by CardTransactionTest).
 */
class PatientCardFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function receptionist(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'receptionist']);
        $u->syncSpatieRole();

        return $u;
    }

    public function test_issue_credit_and_debit_flow(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->receptionist($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post("/admin/patients/{$patient->uuid}/cards", [
            'accepts_credit' => 0, 'max_credit' => 0,
        ])->assertRedirect();

        $card = PatientCard::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)->post("/admin/patients/{$patient->uuid}/cards/{$card->uuid}/credit", [
            'amount' => '150.00', 'description' => 'Deposit',
        ])->assertRedirect();
        $this->assertSame('150.00', (string) $card->fresh()->balance);

        $this->actingAs($user)->post("/admin/patients/{$patient->uuid}/cards/{$card->uuid}/debit", [
            'amount' => '40.00', 'description' => 'Consult',
        ])->assertRedirect();
        $this->assertSame('110.00', (string) $card->fresh()->balance);

        $this->assertDatabaseCount('card_records', 2);
    }

    public function test_overdraft_debit_is_rejected_with_flash_error(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->receptionist($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post("/admin/patients/{$patient->uuid}/cards", []);
        $card = PatientCard::where('patient_id', $patient->id)->firstOrFail();

        $this->actingAs($user)
            ->post("/admin/patients/{$patient->uuid}/cards/{$card->uuid}/debit", ['amount' => '10.00'])
            ->assertSessionHas('error');

        $this->assertSame('0.00', (string) $card->fresh()->balance);
        $this->assertDatabaseCount('card_records', 0);
    }

    public function test_a_role_without_card_permission_cannot_issue(): void
    {
        $h = Hospital::factory()->create();
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        $doctor->syncSpatieRole();
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($doctor)->post("/admin/patients/{$patient->uuid}/cards", [])->assertForbidden();
        $this->assertDatabaseCount('patient_cards', 0);
    }

    public function test_card_of_another_hospital_cannot_be_charged(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $patientB = Patient::factory()->create(['hospital_id' => $b->id]);

        // Issue B's card as B's receptionist.
        $this->actingAs($this->receptionist($b))->post("/admin/patients/{$patientB->uuid}/cards", []);
        $cardB = PatientCard::withoutGlobalScopes()->where('patient_id', $patientB->id)->firstOrFail();

        // A's receptionist can neither see B's patient nor B's card.
        $this->actingAs($this->receptionist($a))
            ->post("/admin/patients/{$patientB->uuid}/cards/{$cardB->uuid}/credit", ['amount' => '100.00'])
            ->assertNotFound();

        $this->assertSame('0.00', (string) $cardB->fresh()->balance);
    }
}
