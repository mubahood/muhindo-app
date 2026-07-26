<?php

namespace Tests\Feature\Admin;

use App\Enums\QuizAttemptStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.3.4 — the course analytics tab: enrollment funnel, per-lesson drop-off, watch-time histogram, quiz summaries. */
class CourseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function enrolled(Course $course, string $status = 'active', int $watchSeconds = 0, ?int $progress = null): Enrollment
    {
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => $status, 'source' => 'self', 'enrolled_at' => now(),
            'total_watch_seconds' => $watchSeconds,
            'progress_percent' => $progress ?? ($status === 'completed' ? 100 : 0),
            'last_accessed_at' => $watchSeconds > 0 ? now() : null,
        ]);
    }

    public function test_a_non_admin_cannot_view_course_analytics(): void
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.courses.analytics', $course))->assertRedirect(route('login'));
    }

    public function test_the_funnel_counts_only_active_and_completed_enrollments_by_stage(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->enrolled($course, 'pending'); // never counted anywhere in the funnel
        $this->enrolled($course, 'active', watchSeconds: 0, progress: 0); // enrolled, not started
        $this->enrolled($course, 'active', watchSeconds: 300, progress: 30); // started, reached 25%
        $this->enrolled($course, 'active', watchSeconds: 600, progress: 60); // reached 50%
        $this->enrolled($course, 'completed', watchSeconds: 900, progress: 100); // completed

        $response = $this->actingAs($admin)->get(route('admin.courses.analytics', $course));

        $response->assertOk();
        $response->assertViewHas('funnel', function (array $funnel) {
            return $funnel['enrolled'] === 4
                && $funnel['started'] === 3
                && $funnel['reached_25'] === 3
                && $funnel['reached_50'] === 2
                && $funnel['reached_75'] === 1
                && $funnel['completed'] === 1
                && $funnel['certified'] === 0;
        });
    }

    public function test_a_certificate_moves_an_enrollment_into_the_certified_stage(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $enrollment = $this->enrolled($course, 'completed', watchSeconds: 100, progress: 100);
        Certificate::create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id,
            'certificate_no' => 'CERT-1', 'issued_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.courses.analytics', $course));

        $response->assertViewHas('funnel', fn (array $funnel) => $funnel['certified'] === 1);
    }

    public function test_per_lesson_drop_off_reflects_completion_rate_in_curriculum_order(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonA = Lesson::create(['course_module_id' => $module->id, 'title' => 'A', 'sort_order' => 0, 'is_published' => true]);
        $lessonB = Lesson::create(['course_module_id' => $module->id, 'title' => 'B', 'sort_order' => 1, 'is_published' => true]);
        $draftLesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Draft', 'sort_order' => 2, 'is_published' => false]);

        $e1 = $this->enrolled($course);
        $e2 = $this->enrolled($course);
        $e1->progressRecords()->create(['lesson_id' => $lessonA->id, 'completed_at' => now()]);
        $e2->progressRecords()->create(['lesson_id' => $lessonA->id, 'completed_at' => now()]);
        $e1->progressRecords()->create(['lesson_id' => $lessonB->id, 'completed_at' => now()]);

        $response = $this->actingAs($admin)->get(route('admin.courses.analytics', $course));

        $response->assertViewHas('dropOff', function (array $dropOff) use ($lessonA, $lessonB, $draftLesson) {
            $ids = array_column($dropOff, 'lesson_id');
            if (in_array($draftLesson->id, $ids, true)) {
                return false; // an unpublished lesson must never appear in the chart
            }

            return $dropOff[0]['lesson_id'] === $lessonA->id && $dropOff[0]['completion_rate'] === 100.0
                && $dropOff[1]['lesson_id'] === $lessonB->id && $dropOff[1]['completion_rate'] === 50.0;
        });
    }

    public function test_the_watch_time_histogram_buckets_enrollments_correctly(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $this->enrolled($course, watchSeconds: 0);
        $this->enrolled($course, watchSeconds: 10 * 60);
        $this->enrolled($course, watchSeconds: 90 * 60);

        $response = $this->actingAs($admin)->get(route('admin.courses.analytics', $course));

        $response->assertViewHas('watchTime', function (array $watchTime) {
            return $watchTime['No watch time'] === 1
                && $watchTime['Under 30 min'] === 1
                && $watchTime['1–2 hrs'] === 1;
        });
    }

    public function test_quiz_summaries_average_only_graded_attempts_and_link_to_item_analysis(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create(['title' => 'Quiz 1', 'pass_percent' => 70, 'is_published' => true]);
        $enrollment = $this->enrolled($course);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 80.0, 'score_points' => 80, 'max_points' => 100, 'passed' => true,
        ]);
        $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 2,
            'status' => QuizAttemptStatus::InProgress, 'started_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.courses.analytics', $course));

        $response->assertViewHas('quizzes', function (array $quizzes) {
            return $quizzes[0]['graded_attempts'] === 1 && $quizzes[0]['average_score_percent'] === 80.0;
        });
        $response->assertSee(route('admin.quizzes.analysis', $quiz), false);
    }
}
