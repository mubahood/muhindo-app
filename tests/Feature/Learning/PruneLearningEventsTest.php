<?php

namespace Tests\Feature\Learning;

use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §6.2 — the retention prune: raw learning_events older than 12 months are deleted; the aggregates they fed are untouched. */
class PruneLearningEventsTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        $course = Course::factory()->create();
        $student = User::factory()->create(['role' => 'student']);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'total_watch_seconds' => 500,
        ]);
    }

    /** `created_at` isn't mass-assignable (it's a domain log, not client input), so it's backdated after the fact. */
    private function eventAt(Enrollment $enrollment, \Illuminate\Support\Carbon $when): int
    {
        $event = $enrollment->learningEvents()->create(['event' => LearningEventType::LessonViewed->value]);
        $event->forceFill(['created_at' => $when])->save();

        return $event->id;
    }

    public function test_events_older_than_twelve_months_are_deleted(): void
    {
        $enrollment = $this->enrollment();
        $oldId = $this->eventAt($enrollment, now()->subMonths(13));

        $this->artisan('app:prune-learning-events')->assertExitCode(0);

        $this->assertDatabaseMissing('learning_events', ['id' => $oldId]);
    }

    public function test_events_within_twelve_months_are_kept(): void
    {
        $enrollment = $this->enrollment();
        $recentId = $this->eventAt($enrollment, now()->subMonths(6));

        $this->artisan('app:prune-learning-events');

        $this->assertDatabaseHas('learning_events', ['id' => $recentId]);
    }

    public function test_an_event_right_at_the_boundary_is_kept(): void
    {
        $enrollment = $this->enrollment();
        $boundaryId = $this->eventAt($enrollment, now()->subMonths(12)->addDay());

        $this->artisan('app:prune-learning-events');

        $this->assertDatabaseHas('learning_events', ['id' => $boundaryId]);
    }

    public function test_pruning_never_touches_the_denormalized_aggregates(): void
    {
        $enrollment = $this->enrollment();
        $this->eventAt($enrollment, now()->subMonths(13));

        $this->artisan('app:prune-learning-events');

        $this->assertSame(500, $enrollment->fresh()->total_watch_seconds);
    }

    public function test_running_with_no_events_at_all_is_a_clean_no_op(): void
    {
        $this->artisan('app:prune-learning-events')->assertExitCode(0);

        $this->assertSame(0, \App\Models\LearningEvent::count());
    }
}
