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

/** L9 — the "My Courses" list must not run 2 extra queries per enrollment row. */
class LearnIndexQueryCountTest extends TestCase
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

        // The view now reads the §6.1 denormalized progress_percent column (the
        // same one ProgressService maintains in real usage) rather than
        // recomputing it live, so this bypass-created fixture needs to set it
        // explicitly to match what a real completion would have written.
        $enrollment->update(['progress_percent' => $enrollment->progressPercent()]);
    }

    public function test_the_my_courses_list_runs_the_same_query_count_regardless_of_enrollment_row_count(): void
    {
        $studentWithOne = User::factory()->create(['role' => 'student']);
        $this->enrollInANewCourse($studentWithOne);

        // The page renders inside the app shell, whose sidebar gates items on
        // permissions — and Spatie loads the permission table once per process.
        // Warm it first, or the first measurement carries a one-off query the
        // second doesn't and the comparison stops being about per-row cost.
        $this->actingAs($studentWithOne)->get(route('learn.index'))->assertOk();

        DB::enableQueryLog();
        $this->actingAs($studentWithOne)->get(route('learn.index'))->assertOk();
        $queriesForOne = count(DB::getQueryLog());
        DB::disableQueryLog();

        $studentWithFive = User::factory()->create(['role' => 'student']);
        for ($i = 0; $i < 5; $i++) {
            $this->enrollInANewCourse($studentWithFive);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($studentWithFive)->get(route('learn.index'))->assertOk();
        $queriesForFive = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(
            $queriesForOne,
            $queriesForFive,
            "Expected a flat query count regardless of enrollment count (got {$queriesForOne} for 1 vs {$queriesForFive} for 5) — a per-row query crept back in."
        );
    }

    public function test_the_my_courses_list_still_renders_the_correct_progress_percentage(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->enrollInANewCourse($student);

        $this->actingAs($student)->get(route('learn.index'))->assertOk()->assertSee('50%');
    }
}
