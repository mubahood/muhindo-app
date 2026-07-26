<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BedStatus;
use App\Exceptions\BedUnavailableException;
use App\Models\Bed;
use App\Models\Consultation;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\Ward;
use App\Services\AdmissionService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdmissionService $svc;

    private Hospital $hospital;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(AdmissionService::class);
        $this->hospital = Hospital::factory()->create();
        app(CurrentHospital::class)->set($this->hospital->id);
    }

    private function bed(string $charge = '50.00'): Bed
    {
        $ward = Ward::factory()->create(['hospital_id' => $this->hospital->id]);

        return Bed::factory()->create(['hospital_id' => $this->hospital->id, 'ward_id' => $ward->id, 'daily_charge' => $charge]);
    }

    private function patient(): Patient
    {
        return Patient::factory()->create(['hospital_id' => $this->hospital->id]);
    }

    public function test_admit_occupies_the_bed(): void
    {
        $bed = $this->bed();
        $admission = $this->svc->admit($this->patient(), $bed, ['reason' => 'Observation']);

        $this->assertSame(AdmissionStatus::Admitted, $admission->status);
        $this->assertSame(BedStatus::Occupied, $bed->fresh()->status);
    }

    public function test_cannot_admit_to_an_occupied_bed(): void
    {
        $bed = $this->bed();
        $this->svc->admit($this->patient(), $bed, []);

        $this->expectException(BedUnavailableException::class);
        $this->svc->admit($this->patient(), $bed->fresh(), []);
    }

    public function test_transfer_frees_old_bed_and_occupies_new(): void
    {
        $bedA = $this->bed();
        $bedB = $this->bed();
        $admission = $this->svc->admit($this->patient(), $bedA, []);

        $this->svc->transfer($admission, $bedB, 'Closer to nurses');

        $this->assertSame(BedStatus::Available, $bedA->fresh()->status);
        $this->assertSame(BedStatus::Occupied, $bedB->fresh()->status);
        $this->assertSame($bedB->id, $admission->fresh()->bed_id);
        $this->assertSame(1, $admission->transfers()->count());
    }

    public function test_discharge_frees_bed_and_bills_nights_to_the_consultation(): void
    {
        $patient = $this->patient();
        $consultation = Consultation::factory()->create(['hospital_id' => $this->hospital->id, 'patient_id' => $patient->id]);
        $bed = $this->bed('40.00');

        $admission = $this->svc->admit($patient, $bed, [
            'consultation_id' => $consultation->id,
            'admitted_at' => Carbon::now()->subDays(3),
        ]);

        $this->svc->discharge($admission->fresh(), AdmissionStatus::Discharged, 'Recovered');

        $admission->refresh();
        $this->assertSame(AdmissionStatus::Discharged, $admission->status);
        $this->assertSame(BedStatus::Available, $bed->fresh()->status);
        $this->assertSame('120.00', (string) $admission->bed_charge_total); // 3 × 40
        // Billed to the consultation.
        $this->assertSame(1, $consultation->medicalServices()->count());
        $this->assertSame('120.00', (string) $consultation->medicalServices()->first()->line_total);
    }

    public function test_cannot_discharge_twice(): void
    {
        $bed = $this->bed();
        $admission = $this->svc->admit($this->patient(), $bed, []);
        $this->svc->discharge($admission->fresh(), AdmissionStatus::Discharged);

        $this->expectException(\RuntimeException::class);
        $this->svc->discharge($admission->fresh(), AdmissionStatus::Discharged);
    }

    public function test_minimum_one_night_is_charged(): void
    {
        $patient = $this->patient();
        $consultation = Consultation::factory()->create(['hospital_id' => $this->hospital->id, 'patient_id' => $patient->id]);
        $bed = $this->bed('30.00');
        $admission = $this->svc->admit($patient, $bed, ['consultation_id' => $consultation->id]); // admitted now

        $this->svc->discharge($admission->fresh(), AdmissionStatus::Discharged);

        $this->assertSame('30.00', (string) $admission->fresh()->bed_charge_total); // same-day = 1 night
    }
}
