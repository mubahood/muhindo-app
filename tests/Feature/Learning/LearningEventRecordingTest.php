<?php

namespace Tests\Feature\Learning;

use App\Enums\LearningEventType;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Server-side hooks for view/complete/download must feed the learning_events stream. */
class LearningEventRecordingTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
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

    public function test_viewing_a_lesson_records_a_lesson_viewed_event(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $this->assertDatabaseHas('learning_events', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'event' => LearningEventType::LessonViewed->value,
        ]);
    }

    public function test_completing_a_lesson_records_a_lesson_completed_event(): void
    {
        Storage::fake('local');
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lesson]));

        $this->assertDatabaseHas('learning_events', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'event' => LearningEventType::LessonCompleted->value,
        ]);
    }

    public function test_downloading_a_stored_material_streams_it_and_records_an_event(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/test.pdf', 'fake-pdf-bytes');
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/test.pdf',
        ]);

        $response = $this->actingAs($student)->get(route('learn.materials.download', [$course, $lesson, $material]));

        $response->assertOk();
        $this->assertDatabaseHas('learning_events', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
            'subject_type' => (new LessonMaterial)->getMorphClass(),
            'subject_id' => $material->id,
            'event' => LearningEventType::MaterialDownloaded->value,
        ]);
    }

    public function test_downloading_a_link_material_redirects_to_the_url_and_still_records_an_event(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->activeEnrollment();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'External resource', 'type' => 'link',
            'file_path' => 'https://example.com/resource',
        ]);

        $response = $this->actingAs($student)->get(route('learn.materials.download', [$course, $lesson, $material]));

        $response->assertRedirect('https://example.com/resource');
        $this->assertDatabaseHas('learning_events', ['enrollment_id' => $enrollment->id, 'event' => LearningEventType::MaterialDownloaded->value]);
    }

    public function test_a_pending_enrollment_cannot_download_a_lesson_material(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        Enrollment::where('user_id', $student->id)->update(['status' => 'pending']);
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/test.pdf',
        ]);

        $this->actingAs($student)->get(route('learn.materials.download', [$course, $lesson, $material]))->assertForbidden();
    }

    public function test_a_material_belonging_to_a_different_lesson_404s(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        $otherModule = CourseModule::create(['course_id' => $course->id, 'title' => 'M2', 'sort_order' => 1]);
        $otherLesson = Lesson::create(['course_module_id' => $otherModule->id, 'title' => 'L2', 'sort_order' => 0]);
        $material = LessonMaterial::create([
            'lesson_id' => $otherLesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/test.pdf',
        ]);

        $this->actingAs($student)->get(route('learn.materials.download', [$course, $lesson, $material]))->assertNotFound();
    }
}
