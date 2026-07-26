<?php

namespace Tests\Feature\Learning;

use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.5 — the weekly streak counter, computed portably in PHP (no MySQL-only YEARWEEK) so it works on SQLite too. */
class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(User $user): Enrollment
    {
        $course = Course::factory()->create();

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
    }

    /** `created_at` isn't mass-assignable (by design — it's a domain event log, not client input), so it's backdated after the fact. */
    private function activityAt(Enrollment $enrollment, \Illuminate\Support\Carbon $when): void
    {
        $event = $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value]);
        $event->forceFill(['created_at' => $when])->save();
    }

    public function test_a_user_with_no_activity_has_no_streak(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->assertSame(0, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_activity_only_this_week_counts_as_a_streak_of_one(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $this->activityAt($this->enrollment($user), now());

        $this->assertSame(1, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_activity_last_week_but_not_yet_this_week_still_counts(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $this->activityAt($this->enrollment($user), now()->subWeek());

        $this->assertSame(1, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_consecutive_weeks_accumulate(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollment($user);
        $this->activityAt($enrollment, now());
        $this->activityAt($enrollment, now()->subWeek());
        $this->activityAt($enrollment, now()->subWeeks(2));

        $this->assertSame(3, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_a_gap_breaks_the_streak_before_the_gap(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollment($user);
        $this->activityAt($enrollment, now());
        $this->activityAt($enrollment, now()->subWeek());
        // Week -2 skipped entirely.
        $this->activityAt($enrollment, now()->subWeeks(3));

        $this->assertSame(2, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_a_gap_of_two_or_more_weeks_ago_resets_the_streak_to_zero(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $this->activityAt($this->enrollment($user), now()->subWeeks(3));

        $this->assertSame(0, app(StreakService::class)->currentWeeklyStreak($user));
    }

    public function test_activity_on_another_students_enrollment_does_not_count(): void
    {
        $user = User::factory()->create(['role' => 'student']);
        $otherUser = User::factory()->create(['role' => 'student']);
        $this->activityAt($this->enrollment($otherUser), now());

        $this->assertSame(0, app(StreakService::class)->currentWeeklyStreak($user));
    }
}
