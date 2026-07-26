<?php

namespace Tests\Feature;

use App\Enums\ConsultationStatus;
use App\Exceptions\InvalidConsultationTransitionException;
use App\Models\Hospital;
use App\Models\Patient;
use App\Services\ConsultationService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ConsultationService $svc;

    private Hospital $hospital;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ConsultationService::class);
        $this->hospital = Hospital::factory()->create();
        app(CurrentHospital::class)->set($this->hospital->id);
        $this->patient = Patient::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    public function test_opens_with_a_generated_number_and_opening_history(): void
    {
        $c = $this->svc->open(['patient_id' => $this->patient->id]);

        $this->assertSame(ConsultationStatus::Registration, $c->status);
        $this->assertMatchesRegularExpression('/^C-\d{8}-\d{3}$/', $c->consultation_no);
        $this->assertDatabaseHas('consultation_status_histories', [
            'consultation_id' => $c->id, 'from_status' => null, 'to_status' => 'registration',
        ]);
    }

    public function test_number_sequence_increments_per_day(): void
    {
        $a = $this->svc->open(['patient_id' => $this->patient->id]);
        $b = $this->svc->open(['patient_id' => $this->patient->id]);

        $this->assertStringEndsWith('-001', $a->consultation_no);
        $this->assertStringEndsWith('-002', $b->consultation_no);
    }

    public function test_records_vitals_and_computes_bmi(): void
    {
        $c = $this->svc->open(['patient_id' => $this->patient->id]);

        $this->svc->recordVitals($c, ['weight' => 80, 'height' => 178, 'temperature' => 36.8, 'blood_pressure' => '120/80']);

        // 80 / (1.78^2) = 25.25
        $this->assertSame('25.25', (string) $c->fresh()->bmi);
        $this->assertNotNull($c->fresh()->vitals_recorded_at);
    }

    public function test_bmi_is_null_without_both_measurements(): void
    {
        $this->assertNull($this->svc->computeBmi(80.0, null));
        $this->assertNull($this->svc->computeBmi(null, 178.0));
        $this->assertNull($this->svc->computeBmi(80.0, 0.0));
        $this->assertSame(25.25, $this->svc->computeBmi(80.0, 178.0));
    }

    public function test_pipeline_happy_path_and_history(): void
    {
        $c = $this->svc->open(['patient_id' => $this->patient->id]);
        foreach ([
            ConsultationStatus::Triage, ConsultationStatus::Consultation, ConsultationStatus::Orders,
            ConsultationStatus::Billing, ConsultationStatus::Payment, ConsultationStatus::Completed,
        ] as $s) {
            $this->svc->transition($c, $s);
        }

        $this->assertSame(ConsultationStatus::Completed, $c->fresh()->status);
        $this->assertNotNull($c->fresh()->completed_at);
        $this->assertSame(7, $c->history()->count()); // open + 6 transitions
    }

    public function test_rejects_a_skip_ahead_transition(): void
    {
        $c = $this->svc->open(['patient_id' => $this->patient->id]);

        $this->expectException(InvalidConsultationTransitionException::class);
        $this->svc->transition($c, ConsultationStatus::Payment); // registration → payment not allowed
    }

    public function test_cancelled_is_terminal(): void
    {
        $c = $this->svc->open(['patient_id' => $this->patient->id]);
        $this->svc->transition($c, ConsultationStatus::Cancelled);

        $this->expectException(InvalidConsultationTransitionException::class);
        $this->svc->transition($c, ConsultationStatus::Triage);
    }
}
