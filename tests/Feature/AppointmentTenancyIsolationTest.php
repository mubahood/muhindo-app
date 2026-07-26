<?php

namespace Tests\Feature;

use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentService;
use App\Support\CurrentHospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §2.1 — scheduling ships an A-vs-B isolation test: hospital A can neither see
 * nor act on hospital B's appointments or availability, and booking can never
 * reference another hospital's doctor/patient.
 */
class AppointmentTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
    }

    private function admin(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'hospital_admin']);
        $u->syncSpatieRole();

        return $u;
    }

    private function bookFor(Hospital $h): Appointment
    {
        app(CurrentHospital::class)->set($h->id);
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        DoctorSchedule::factory()->create([
            'hospital_id' => $h->id, 'user_id' => $doctor->id,
            'weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30,
        ]);
        $patient = Patient::factory()->create(['hospital_id' => $h->id, 'first_name' => 'Zeb', 'last_name' => 'FromB']);

        return app(AppointmentService::class)->book([
            'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'scheduled_at' => '2026-08-03 09:00', 'duration_minutes' => 30,
        ]);
    }

    public function test_hospital_a_cannot_see_or_act_on_bs_appointment(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $apptB = $this->bookFor($b);
        $adminA = $this->admin($a);

        $this->actingAs($adminA)->get('/admin/appointments?date=2026-08-03')->assertOk()->assertDontSee('FromB');
        $this->actingAs($adminA)->get("/admin/appointments/{$apptB->uuid}")->assertNotFound();
        $this->actingAs($adminA)->post("/admin/appointments/{$apptB->uuid}/transition", ['status' => 'cancelled'])->assertNotFound();
    }

    public function test_cannot_book_against_another_hospitals_doctor(): void
    {
        $a = Hospital::factory()->create();
        $b = Hospital::factory()->create();
        $doctorB = User::factory()->create(['hospital_id' => $b->id, 'role' => 'doctor']);
        $patientA = Patient::factory()->create(['hospital_id' => $a->id]);

        $this->actingAs($this->admin($a))->post('/admin/appointments', [
            'patient_id' => $patientA->id, 'doctor_user_id' => $doctorB->id,
            'scheduled_at' => '2026-08-03 09:00', 'duration_minutes' => 30, 'source' => 'walk_in',
        ])->assertSessionHasErrors('doctor_user_id');

        $this->assertDatabaseCount('appointments', 0);
    }
}
