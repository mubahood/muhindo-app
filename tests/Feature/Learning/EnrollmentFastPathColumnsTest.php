<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The denormalized fast-path columns must stay in sync with real progress. */
class EnrollmentFastPathColumnsTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollmentWithTwoLessons(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ])->fresh();

        $this->actingAs($student);

        return [$course, $lessonOne, $lessonTwo, $enrollment];
    }

    public function test_enrollments_has_an_index_on_last_accessed_at(): void
    {
        $hasIndex = collect(Schema::getIndexes('enrollments'))
            ->contains(fn (array $index) => $index['columns'] === ['last_accessed_at']);

        $this->assertTrue($hasIndex, 'Expected an index on enrollments.last_accessed_at.');
    }

    public function test_viewing_a_lesson_records_last_lesson_and_last_accessed_at(): void
    {
        [, $lessonOne, , $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $this->assertNull($enrollment->last_accessed_at);

        app(ProgressService::class)->recordView($enrollment, $lessonOne);
        $enrollment->refresh();

        $this->assertSame($lessonOne->id, $enrollment->last_lesson_id);
        $this->assertNotNull($enrollment->last_accessed_at);
    }

    public function test_completing_a_lesson_updates_the_denormalized_progress_percent(): void
    {
        Storage::fake('local');
        [, $lessonOne, , $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $this->assertSame(0, $enrollment->progress_percent);

        app(ProgressService::class)->completeLesson($enrollment, $lessonOne);
        $enrollment->refresh();

        $this->assertSame(50, $enrollment->progress_percent);
        $this->assertSame($lessonOne->id, $enrollment->last_lesson_id);
        $this->assertNotNull($enrollment->last_accessed_at);
    }

    public function test_completing_the_final_lesson_brings_the_denormalized_percent_to_100(): void
    {
        Storage::fake('local');
        [, $lessonOne, $lessonTwo, $enrollment] = $this->activeEnrollmentWithTwoLessons();

        $service = app(ProgressService::class);
        $service->completeLesson($enrollment, $lessonOne);
        $service->completeLesson($enrollment, $lessonTwo);
        $enrollment->refresh();

        $this->assertSame(100, $enrollment->progress_percent);
        $this->assertSame('completed', $enrollment->status);
    }
}
