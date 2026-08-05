<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** "complete without reload": one route, JSON for AJAX, a redirect for a plain form POST. */
class AjaxLessonCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollment(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'Eloquent Relationships', 'sort_order' => 1]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$course, $lessonOne, $lessonTwo, $student, $enrollment];
    }

    public function test_an_ajax_completion_returns_json_with_the_next_lesson(): void
    {
        [$course, $lessonOne, $lessonTwo, $student] = $this->activeEnrollment();

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lessonOne]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'progress_percent' => 50,
                'course_completed' => false,
                'next_lesson_title' => 'Eloquent Relationships',
            ])
            ->assertJsonPath('next_lesson_url', route('learn.lesson', [$course, $lessonTwo]));
    }

    public function test_an_ajax_completion_of_the_final_lesson_returns_the_certificate_url(): void
    {
        Storage::fake('local');
        [$course, $lessonOne, $lessonTwo, $student] = $this->activeEnrollment();
        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lessonOne]));

        $response = $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lessonTwo]))
            ->assertOk()
            ->assertJson(['success' => true, 'progress_percent' => 100, 'course_completed' => true, 'next_lesson_url' => null]);

        $this->assertNotNull($response->json('certificate_url'));
    }

    public function test_a_plain_form_post_without_js_still_redirects(): void
    {
        [$course, $lessonOne, $lessonTwo, $student] = $this->activeEnrollment();

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lessonOne]))
            ->assertRedirect(route('learn.lesson', [$course, $lessonTwo]));
    }

    public function test_an_ajax_completion_of_a_locked_lesson_returns_a_json_403(): void
    {
        [$course, , $lessonTwo, $student] = $this->activeEnrollment();
        $course->update(['progression' => \App\Enums\CourseProgression::Sequential]);

        $this->actingAs($student)->postJson(route('learn.lesson.complete', [$course, $lessonTwo]))
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
