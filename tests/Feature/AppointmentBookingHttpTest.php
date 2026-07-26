<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\Weekday;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Hospital;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentBookingHttpTest extends TestCase
{
    use RefreshDatabase;

    private const MON_0900 = '2026-08-03 09:00';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $this->assertSame(Weekday::Monday->value, Carbon::parse(self::MON_0900)->dayOfWeek);
    }

    private function receptionist(Hospital $h): User
    {
        $u = User::factory()->create(['hospital_id' => $h->id, 'role' => 'receptionist']);
        $u->syncSpatieRole();

        return $u;
    }

    private function seedDoctorWindow(Hospital $h): User
    {
        $doctor = User::factory()->create(['hospital_id' => $h->id, 'role' => 'doctor']);
        DoctorSchedule::factory()->create([
            'hospital_id' => $h->id, 'user_id' => $doctor->id,
            'weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'slot_minutes' => 30,
        ]);

        return $doctor;
    }

    public function test_receptionist_books_and_then_advances_status(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->receptionist($h);
        $doctor = $this->seedDoctorWindow($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post('/admin/appointments', [
            'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'scheduled_at' => self::MON_0900, 'duration_minutes' => 30, 'source' => 'phone',
        ])->assertRedirect();

        $appt = Appointment::firstOrFail();
        $this->assertSame(AppointmentStatus::Scheduled, $appt->status);

        $this->actingAs($user)->post("/admin/appointments/{$appt->uuid}/transition", ['status' => 'checked_in'])
            ->assertRedirect();
        $this->assertSame(AppointmentStatus::CheckedIn, $appt->fresh()->status);
        $this->assertNotNull($appt->fresh()->checked_in_at);
    }

    public function test_double_booking_is_rejected_with_a_flash_error(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->receptionist($h);
        $doctor = $this->seedDoctorWindow($h);
        $p1 = Patient::factory()->create(['hospital_id' => $h->id]);
        $p2 = Patient::factory()->create(['hospital_id' => $h->id]);

        $this->actingAs($user)->post('/admin/appointments', [
            'patient_id' => $p1->id, 'doctor_user_id' => $doctor->id,
            'scheduled_at' => self::MON_0900, 'duration_minutes' => 30, 'source' => 'walk_in',
        ]);

        $this->actingAs($user)->post('/admin/appointments', [
            'patient_id' => $p2->id, 'doctor_user_id' => $doctor->id,
            'scheduled_at' => self::MON_0900, 'duration_minutes' => 30, 'source' => 'walk_in',
        ])->assertSessionHas('error');

        $this->assertSame(1, Appointment::count());
    }

    public function test_illegal_transition_is_rejected_with_a_flash_error(): void
    {
        $h = Hospital::factory()->create();
        $user = $this->receptionist($h);
        $doctor = $this->seedDoctorWindow($h);
        $patient = Patient::factory()->create(['hospital_id' => $h->id]);
        $this->actingAs($user)->post('/admin/appointments', [
            'patient_id' => $patient->id, 'doctor_user_id' => $doctor->id,
            'scheduled_at' => self::MON_0900, 'duration_minutes' => 30, 'source' => 'walk_in',
        ]);
        $appt = Appointment::firstOrFail();

        $this->actingAs($user)->post("/admin/appointments/{$appt->uuid}/transition", ['status' => 'completed'])
            ->assertSessionHas('error');
        $this->assertSame(AppointmentStatus::Scheduled, $appt->fresh()->status);
    }

    public function test_nurse_cannot_book_but_can_view(): void
    {
        $h = Hospital::factory()->create();
        $nurse = User::factory()->create(['hospital_id' => $h->id, 'role' => 'nurse']);
        $nurse->syncSpatieRole();

        $this->actingAs($nurse)->get('/admin/appointments')->assertOk();
        $this->actingAs($nurse)->get('/admin/appointments/create')->assertForbidden();
    }
}
