<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §9 — due dates/time windows are stored in UTC (config('app.timezone')) but must render in Africa/Kampala (UTC+3). */
class DueDateTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_carbon_macro_shifts_utc_to_kampala_time(): void
    {
        $utc = Carbon::parse('2026-08-01 09:00:00', 'UTC');

        $local = $utc->toLocal();

        $this->assertSame('Africa/Kampala', $local->timezone->getName());
        $this->assertSame('12:00', $local->format('H:i'));
    }

    public function test_an_assignments_due_date_renders_in_kampala_time_not_utc(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $assignment = $course->assignments()->create([
            'title' => 'A1', 'points' => 50, 'max_file_mb' => 20, 'allowed_types' => 'text', 'is_published' => true,
            'due_at' => Carbon::parse('2026-08-01 09:00:00', 'UTC'),
        ]);

        $this->actingAs($student)->get(route('learn.assignment.show', [$course, $assignment]))
            ->assertOk()
            ->assertSee('Due Aug 1, 2026 12:00pm');
    }
}
