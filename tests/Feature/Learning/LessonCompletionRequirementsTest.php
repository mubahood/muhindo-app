<?php

namespace Tests\Feature\Learning;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lesson completion requirements, minimum focused time (lessons.min_active_seconds)
 * and compulsory activities (quizzes/assignments.is_required), enforced in
 * ProgressService::completeLesson for every surface (web, API, min-watch auto-complete).
 */
class LessonCompletionRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(array $lessonAttrs = []): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge(
            ['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0, 'is_published' => true],
            $lessonAttrs,
        ));
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student, $enrollment];
    }

    private function requiredQuiz(Course $course, Lesson $lesson, bool $required = true): Quiz
    {
        return Quiz::create([
            'course_id' => $course->id, 'lesson_id' => $lesson->id, 'title' => 'Gate Quiz',
            'is_required' => $required, 'is_published' => true,
        ]);
    }

    public function test_completion_is_blocked_until_the_minimum_time_is_met(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment(['min_active_seconds' => 120]);

        $response = $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]));

        $response->assertStatus(422);
        $response->assertJsonPath('blockers.0.type', 'min_time');
        $response->assertJsonPath('blockers.0.remaining_seconds', 120);
        $this->assertNull($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('completed_at'));
    }

    public function test_completion_succeeds_once_enough_focused_time_is_recorded(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment(['min_active_seconds' => 30]);

        // Two clamped beats = 60s of focused time.
        $this->actingAs($student)->postJson(route('learn.lesson.time', [$course, $lesson]), ['active_delta' => 30]);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();
        $this->assertNotNull($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('completed_at'));
    }

    public function test_a_plain_form_post_redirects_back_with_the_error(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment(['min_active_seconds' => 120]);

        $response = $this->actingAs($student)
            ->from(route('learn.lesson', [$course, $lesson]))
            ->post(route('learn.lesson.complete', [$course, $lesson]));

        $response->assertRedirect(route('learn.lesson', [$course, $lesson]));
        $response->assertSessionHas('error');
    }

    public function test_a_required_unsubmitted_quiz_blocks_completion(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $this->requiredQuiz($course, $lesson);

        $response = $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]));

        $response->assertStatus(422);
        $response->assertJsonPath('blockers.0.type', 'quiz');
        $response->assertJsonPath('blockers.0.title', 'Gate Quiz');
    }

    public function test_an_optional_quiz_never_blocks_completion(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        $this->requiredQuiz($course, $lesson, required: false);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();
    }

    public function test_a_submitted_attempt_unblocks_the_required_quiz(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $quiz = $this->requiredQuiz($course, $lesson);
        QuizAttempt::create([
            'uuid' => (string) Str::uuid(), 'quiz_id' => $quiz->id, 'enrollment_id' => $enrollment->id,
            'attempt_no' => 1, 'status' => 'submitted', 'started_at' => now(), 'submitted_at' => now(),
        ]);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();
    }

    public function test_a_required_assignment_blocks_until_submitted(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $assignment = Assignment::create([
            'course_id' => $course->id, 'lesson_id' => $lesson->id, 'title' => 'Gate Assignment',
            'points' => 10, 'is_required' => true, 'is_published' => true,
        ]);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))
            ->assertStatus(422)->assertJsonPath('blockers.0.type', 'assignment');

        AssignmentSubmission::create([
            'uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'enrollment_id' => $enrollment->id,
            'attempt_no' => 1, 'body' => 'my code', 'status' => 'submitted', 'submitted_at' => now(),
        ]);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();
    }

    public function test_min_watch_auto_complete_is_deferred_while_a_required_quiz_is_pending(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment([
            'completion_rule' => 'min_watch', 'completion_threshold' => 1, 'duration_minutes' => 1,
        ]);
        $this->requiredQuiz($course, $lesson);

        // Crosses the min-watch threshold, which would normally auto-complete.
        $this->actingAs($student)->postJson(route('learn.lesson.heartbeat', [$course, $lesson]), [
            'seconds_delta' => 30, 'position_seconds' => 30,
        ])->assertOk()->assertJson(['completed' => false]);

        $this->assertNull($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('completed_at'));
    }

    public function test_an_already_completed_lesson_bypasses_new_blockers(): void
    {
        // The "Next lesson" button re-posts complete on completed lessons (idempotent
        // stale-tab path). A requirement added later must never break it.
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();

        $lesson->update(['min_active_seconds' => 600]);
        $this->requiredQuiz($course, $lesson);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lesson]))->assertOk();
    }

    public function test_the_api_surface_is_gated_too(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment(['min_active_seconds' => 120]);
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson("/api/v1/lessons/{$lesson->id}/complete");

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }

    public function test_the_lesson_page_shows_the_activities_banner_with_the_required_badge(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        $this->requiredQuiz($course, $lesson);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk();
        $response->assertSee('This lesson has 1 activity');
        $response->assertSee('1 required');
        $response->assertSee('Gate Quiz');
        $response->assertSee('requiredPending\\u0022:1', false);
    }

    public function test_the_lesson_page_seeds_the_min_time_lock(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment(['min_active_seconds' => 180]);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk()->assertSee('minActiveSeconds\\u0022:180', false);
    }

    public function test_admin_can_set_the_minimum_time_in_minutes(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.lessons.update', $lesson), [
            'title' => 'L1', 'min_active_minutes' => 3,
        ])->assertRedirect();

        $this->assertSame(180, $lesson->fresh()->min_active_seconds);
    }
}
