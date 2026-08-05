<?php

namespace Tests\Feature\Learning;

use App\Enums\BadgeType;
use App\Enums\QuizAttemptStatus;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\BadgeService;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Badge awards: idempotent, correctly gated, and wired to the real events they fire from. */
class BadgeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function completedEnrollment(User $user): Enrollment
    {
        $course = Course::factory()->create(['is_published' => true]);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $course->id,
            'status' => 'completed', 'source' => 'self', 'enrolled_at' => now(), 'completed_at' => now(),
        ]);
    }

    public function test_completing_one_course_awards_only_the_first_course_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->completedEnrollment($student);

        app(BadgeService::class)->awardCourseCompletionBadges($student);

        $this->assertTrue($student->badges()->where('badge_type', BadgeType::FirstCourseCompleted->value)->exists());
        $this->assertFalse($student->badges()->where('badge_type', BadgeType::FiveCoursesCompleted->value)->exists());
    }

    public function test_completing_five_courses_also_awards_the_five_course_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        for ($i = 0; $i < 5; $i++) {
            $this->completedEnrollment($student);
        }

        app(BadgeService::class)->awardCourseCompletionBadges($student);

        $this->assertTrue($student->badges()->where('badge_type', BadgeType::FirstCourseCompleted->value)->exists());
        $this->assertTrue($student->badges()->where('badge_type', BadgeType::FiveCoursesCompleted->value)->exists());
    }

    public function test_awarding_the_same_badge_twice_does_not_duplicate_the_row(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->completedEnrollment($student);
        $badges = app(BadgeService::class);

        $badges->awardCourseCompletionBadges($student);
        $badges->awardCourseCompletionBadges($student);

        $this->assertSame(1, $student->badges()->where('badge_type', BadgeType::FirstCourseCompleted->value)->count());
    }

    public function test_completing_the_final_lesson_of_a_course_awards_the_first_course_badge_via_the_real_event(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($student);
        app(ProgressService::class)->completeLesson($enrollment, $lesson);

        $this->assertTrue($student->badges()->where('badge_type', BadgeType::FirstCourseCompleted->value)->exists());
    }

    public function test_a_perfect_quiz_score_awards_the_perfect_quiz_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->completedEnrollment($student);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 100.0, 'score_points' => 100, 'max_points' => 100, 'passed' => true,
        ]);

        app(BadgeService::class)->awardPerfectQuizBadgeIfEarned($attempt);

        $this->assertTrue($student->badges()->where('badge_type', BadgeType::PerfectQuiz->value)->exists());
    }

    public function test_a_near_perfect_quiz_score_does_not_award_the_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->completedEnrollment($student);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::Graded, 'started_at' => now(), 'submitted_at' => now(), 'graded_at' => now(),
            'score_percent' => 99.0, 'score_points' => 99, 'max_points' => 100, 'passed' => true,
        ]);

        app(BadgeService::class)->awardPerfectQuizBadgeIfEarned($attempt);

        $this->assertFalse($student->badges()->where('badge_type', BadgeType::PerfectQuiz->value)->exists());
    }

    public function test_the_student_dashboard_renders_earned_badges_and_the_streak_counter(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $this->completedEnrollment($student);
        app(BadgeService::class)->awardCourseCompletionBadges($student);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('First Course Completed')
            ->assertSee('Week streak');
    }

    public function test_an_in_progress_attempt_never_awards_the_perfect_quiz_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->completedEnrollment($student);
        $quiz = $enrollment->course->quizzes()->create(['title' => 'Q1', 'pass_percent' => 70, 'is_published' => true]);
        $attempt = $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(), 'enrollment_id' => $enrollment->id, 'attempt_no' => 1,
            'status' => QuizAttemptStatus::InProgress, 'started_at' => now(),
        ]);

        app(BadgeService::class)->awardPerfectQuizBadgeIfEarned($attempt);

        $this->assertFalse($student->badges()->where('badge_type', BadgeType::PerfectQuiz->value)->exists());
    }
}
