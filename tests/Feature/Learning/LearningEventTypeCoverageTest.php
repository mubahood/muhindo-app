<?php

namespace Tests\Feature\Learning;

use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\LearningEventRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every LearningEventType must actually be storable.
 *
 * `learning_events.event` was a MySQL ENUM frozen at the moment the table was
 * created. LearningEventType later gained a case and the column did not, so
 * submitting an assignment died on "Data truncated for column 'event'" — after
 * the submission row was already written, leaving the student with a 500 having
 * genuinely handed in their work.
 *
 * The suite could not have caught it: it runs on SQLite, where an ENUM column
 * is reported as plain varchar and enforces nothing. Both guards here therefore
 * only bite against MySQL — the recorder walk-through would have thrown on the
 * truncation, and the schema check reads the real column type. On SQLite the
 * first still proves every case round-trips, and the second skips loudly rather
 * than passing quietly.
 */
class LearningEventTypeCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(): Enrollment
    {
        $user = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $course = Course::factory()->create(['is_published' => true]);
        CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);

        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    public function test_every_event_type_can_be_recorded_and_read_back(): void
    {
        $enrollment = $this->enrollment();
        $recorder = app(LearningEventRecorder::class);

        foreach (LearningEventType::cases() as $case) {
            $event = $recorder->record($enrollment, $case);

            $this->assertSame(
                $case,
                LearningEvent::findOrFail($event->id)->event,
                "{$case->value} did not survive a write and read"
            );
        }

        $this->assertSame(count(LearningEventType::cases()), LearningEvent::count());
    }

    public function test_the_column_does_not_keep_its_own_copy_of_the_enum(): void
    {
        /*
         * Only MySQL can answer this. SQLite reports an ENUM column as plain
         * 'varchar' — measured, not assumed — so on the suite's usual driver
         * this assertion passes against the very schema that caused the bug.
         * It skips loudly rather than passing quietly, because a test that
         * cannot fail is worse than no test at all.
         */
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped(
                'ENUM columns are indistinguishable from varchar on '
                .Schema::getConnection()->getDriverName()
                .'; this guard only means something against MySQL.'
            );
        }

        $type = strtolower((string) Schema::getConnection()
            ->selectOne('SHOW COLUMNS FROM learning_events LIKE "event"')->Type);

        $this->assertStringNotContainsString('enum(', $type,
            'event must not be an enum column — the PHP enum is the only list of these values');
    }

    public function test_the_recorder_will_not_take_anything_but_a_known_type(): void
    {
        // The reason the database needs no opinion: nothing else can get in.
        $record = new \ReflectionMethod(LearningEventRecorder::class, 'record');
        $eventParam = $record->getParameters()[1];

        $this->assertSame(LearningEventType::class, (string) $eventParam->getType());
    }

    public function test_submitting_an_assignment_records_its_event(): void
    {
        $enrollment = $this->enrollment();
        $module = $enrollment->course->modules->first();

        $lesson = Lesson::create([
            'course_module_id' => $module->id,
            'title' => 'A topic',
            'content' => 'Read this.',
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $assignment = \App\Models\Assignment::create([
            'course_id' => $enrollment->course_id,
            'lesson_id' => $lesson->id,
            'title' => 'Build a contact page',
            'instructions' => 'Do it.',
            'is_published' => true,
            'points' => 30,
            'allowed_types' => 'text,link,zip',
        ]);

        $this->actingAs($enrollment->user)->post(
            route('learn.assignment.submit', [$enrollment->course, $assignment]),
            ['body' => 'Here is my work.']
        )->assertRedirect();

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'status' => 'submitted',
        ]);

        // The step that used to blow up, after the submission had been saved.
        $this->assertDatabaseHas('learning_events', [
            'enrollment_id' => $enrollment->id,
            'event' => LearningEventType::AssignmentSubmitted->value,
        ]);
    }
}
