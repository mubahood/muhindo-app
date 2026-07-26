<?php

namespace Tests\Feature\Learning;

use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.4 — nightly rules-first at-risk tagging: inactive and stalled. */
class DetectAtRiskEnrollmentsTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(array $overrides = []): Enrollment
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now()->subDays(30),
        ], $overrides));
    }

    public function test_an_enrollment_never_accessed_since_enrolling_long_ago_is_flagged_inactive(): void
    {
        $enrollment = $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);

        $this->artisan('app:detect-at-risk-enrollments')->assertExitCode(0);

        $this->assertSame('inactive', $enrollment->fresh()->at_risk_reason);
    }

    public function test_a_freshly_enrolled_student_with_no_activity_yet_is_not_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['enrolled_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_inactive_for_over_14_days_is_flagged_inactive(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDays(15)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('inactive', $enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_active_with_recent_completions_is_not_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_an_enrollment_active_but_with_no_completions_in_3_weeks_is_flagged_stalled(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay()]);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value, 'created_at' => now()->subDays(2)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame('stalled', $enrollment->fresh()->at_risk_reason);
    }

    public function test_a_previously_flagged_enrollment_is_cleared_once_it_becomes_healthy_again(): void
    {
        $enrollment = $this->activeEnrollment(['last_accessed_at' => now()->subDay(), 'at_risk_reason' => 'stalled']);
        $enrollment->learningEvents()->create(['event' => LearningEventType::LessonCompleted->value, 'created_at' => now()->subDay()]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_a_completed_enrollment_is_never_flagged(): void
    {
        $enrollment = $this->activeEnrollment(['status' => 'completed', 'last_accessed_at' => now()->subDays(60)]);

        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertNull($enrollment->fresh()->at_risk_reason);
    }

    public function test_the_dashboard_counter_reflects_the_flagged_count(): void
    {
        $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);
        $this->activeEnrollment(['enrolled_at' => now()->subDays(30)]);
        $this->artisan('app:detect-at-risk-enrollments');

        $this->assertSame(2, app(\App\Support\Dashboard\DashboardService::class)->atRiskEnrollmentsCount());
    }
}
