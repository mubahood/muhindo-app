<?php

namespace Tests\Feature\Learning;

use App\Enums\BadgeType;
use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.5 — the nightly command that awards the 4-week-streak badge. */
class AwardStreakBadgesTest extends TestCase
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

    public function test_a_four_week_streak_earns_the_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollment($student);
        foreach (range(0, 3) as $weeksAgo) {
            $event = $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value]);
            $event->forceFill(['created_at' => now()->subWeeks($weeksAgo)])->save();
        }

        $this->artisan('app:award-streak-badges')->assertExitCode(0);

        $this->assertTrue($student->badges()->where('badge_type', BadgeType::FourWeekStreak->value)->exists());
    }

    public function test_a_three_week_streak_does_not_yet_earn_the_badge(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->enrollment($student);
        foreach (range(0, 2) as $weeksAgo) {
            $event = $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value]);
            $event->forceFill(['created_at' => now()->subWeeks($weeksAgo)])->save();
        }

        $this->artisan('app:award-streak-badges');

        $this->assertFalse($student->badges()->where('badge_type', BadgeType::FourWeekStreak->value)->exists());
    }

    public function test_a_student_with_no_enrollments_is_skipped_without_error(): void
    {
        User::factory()->create(['role' => 'student']);

        $this->artisan('app:award-streak-badges')->assertExitCode(0);
    }

    public function test_admins_and_clients_are_never_evaluated(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);

        $this->artisan('app:award-streak-badges')->assertExitCode(0);

        $this->assertSame(0, $admin->badges()->count());
    }
}
