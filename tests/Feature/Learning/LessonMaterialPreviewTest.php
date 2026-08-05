<?php

namespace Tests\Feature\Learning;

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

/** Inline PDF preview for the lesson player's Materials card, never forces a download dialog. */
class LessonMaterialPreviewTest extends TestCase
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

    public function test_an_enrolled_student_can_preview_a_pdf_material_inline(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/slides.pdf', '%PDF-1.4 fake bytes');
        [$course, $lesson, $student] = $this->activeEnrollment();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/slides.pdf',
        ]);

        $response = $this->actingAs($student)->get(route('learn.materials.preview', [$course, $lesson, $material]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringNotContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_a_non_pdf_material_404s_on_preview(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/archive.zip', 'fake-zip-bytes');
        [$course, $lesson, $student] = $this->activeEnrollment();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'Assets', 'type' => 'zip', 'file_path' => 'lesson-materials/archive.zip',
        ]);

        $this->actingAs($student)->get(route('learn.materials.preview', [$course, $lesson, $material]))->assertNotFound();
    }

    public function test_a_pdf_material_that_is_actually_an_external_link_404s_on_preview(): void
    {
        [$course, $lesson, $student] = $this->activeEnrollment();
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'External PDF', 'type' => 'pdf', 'file_path' => 'https://example.com/handout.pdf',
        ]);

        $this->actingAs($student)->get(route('learn.materials.preview', [$course, $lesson, $material]))->assertNotFound();
    }

    public function test_a_pending_enrollment_cannot_preview_a_material(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/slides.pdf', '%PDF-1.4 fake bytes');
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'pending', 'source' => 'self',
        ]);
        $material = LessonMaterial::create([
            'lesson_id' => $lesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/slides.pdf',
        ]);

        $this->actingAs($student)->get(route('learn.materials.preview', [$course, $lesson, $material]))->assertForbidden();
    }

    public function test_a_material_belonging_to_a_different_lesson_404s_on_preview(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/slides.pdf', '%PDF-1.4 fake bytes');
        [$course, $lesson, $student] = $this->activeEnrollment();
        $module = $lesson->module;
        $otherLesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $material = LessonMaterial::create([
            'lesson_id' => $otherLesson->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'lesson-materials/slides.pdf',
        ]);

        $this->actingAs($student)->get(route('learn.materials.preview', [$course, $lesson, $material]))->assertNotFound();
    }

    public function test_the_lesson_page_shows_an_inline_preview_iframe_only_for_the_local_pdf_material(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lesson-materials/slides.pdf', '%PDF-1.4 fake bytes');
        [$course, $lesson, $student] = $this->activeEnrollment();
        $pdf = LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Slides PDF', 'type' => 'pdf', 'file_path' => 'lesson-materials/slides.pdf']);
        LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'Project Files', 'type' => 'zip', 'file_path' => 'lesson-materials/files.zip']);
        $externalPdf = LessonMaterial::create(['lesson_id' => $lesson->id, 'title' => 'External Handout', 'type' => 'pdf', 'file_path' => 'https://example.com/handout.pdf']);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk();
        $response->assertSee('Slides PDF');
        $response->assertSee('Project Files');
        $response->assertSee('External Handout');
        $response->assertSee(route('learn.materials.preview', [$course, $lesson, $pdf]), false);
        $response->assertDontSee(route('learn.materials.preview', [$course, $lesson, $externalPdf]), false);
    }
}
