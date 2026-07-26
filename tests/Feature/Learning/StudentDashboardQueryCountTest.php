<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** L9 — the student dashboard's course cards must not run 2 extra queries per enrollment row. */
class StudentDashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private function enrollInANewCourse(User $student): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);
    }

    public function test_the_student_dashboard_runs_the_same_query_count_regardless_of_enrollment_row_count(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollInANewCourse($student);

        // Warm-up request: Spatie's permission/role cache and any other static
        // memoization populate here so it can't masquerade as a query-count delta
        // in the measurements below.
        $this->actingAs($student)->get(route('dashboard'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($student)->get(route('dashboard'))->assertOk();
        $queriesForOne = count(DB::getQueryLog());
        DB::disableQueryLog();

        for ($i = 0; $i < 4; $i++) {
            $this->enrollInANewCourse($student);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($student)->get(route('dashboard'))->assertOk();
        $queriesForFive = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queriesForOne,
            $queriesForFive,
            "Expected a flat query count regardless of enrollment count (got {$queriesForOne} for 1 vs {$queriesForFive} for 5) — a per-row query crept back in."
        );
    }

    public function test_the_student_dashboard_still_renders_the_correct_progress_percentage(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollInANewCourse($student);

        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertSee('50% complete');
    }
}
