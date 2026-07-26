<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §7.5 — the curriculum builder: publish toggle, quick-add, drag-drop reorder, duration auto-fetch. */
class CurriculumBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function moduleWithLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0])->fresh();

        return [$course, $module, $lesson];
    }

    public function test_toggling_publish_flips_the_flag(): void
    {
        $admin = $this->admin();
        [, , $lesson] = $this->moduleWithLesson();
        $this->assertTrue($lesson->is_published);

        $this->actingAs($admin)->post(route('admin.lessons.toggle-publish', $lesson))
            ->assertRedirect(route('admin.courses.show', $lesson->module->course));
        $this->assertFalse($lesson->fresh()->is_published);

        $this->actingAs($admin)->post(route('admin.lessons.toggle-publish', $lesson));
        $this->assertTrue($lesson->fresh()->is_published);
    }

    public function test_an_unpublished_lesson_is_invisible_to_an_enrolled_student(): void
    {
        $admin = $this->admin();
        [$course, , $lesson] = $this->moduleWithLesson();
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.lessons.toggle-publish', $lesson));

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertNotFound();
    }

    public function test_an_unpublished_lesson_does_not_count_toward_course_lesson_count(): void
    {
        $admin = $this->admin();
        [$course, $module, $lesson] = $this->moduleWithLesson();
        Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
        $this->assertSame(2, $course->lessonCount());

        $this->actingAs($admin)->post(route('admin.lessons.toggle-publish', $lesson));

        $this->assertSame(1, $course->fresh()->lessonCount());
    }

    public function test_quick_add_creates_a_draft_lesson(): void
    {
        $admin = $this->admin();
        [$course, $module] = $this->moduleWithLesson();

        $this->actingAs($admin)->post(route('admin.modules.lessons.quick-store', $module), ['title' => 'Quick lesson'])
            ->assertRedirect(route('admin.courses.show', $course));

        $lesson = Lesson::where('title', 'Quick lesson')->first();
        $this->assertNotNull($lesson);
        $this->assertFalse($lesson->is_published);
    }

    public function test_reordering_modules_and_lessons_persists_in_one_request(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $moduleA = CourseModule::create(['course_id' => $course->id, 'title' => 'A', 'sort_order' => 0]);
        $moduleB = CourseModule::create(['course_id' => $course->id, 'title' => 'B', 'sort_order' => 1]);
        $lessonA1 = Lesson::create(['course_module_id' => $moduleA->id, 'title' => 'A1', 'sort_order' => 0]);
        $lessonA2 = Lesson::create(['course_module_id' => $moduleA->id, 'title' => 'A2', 'sort_order' => 1]);

        $this->actingAs($admin)->postJson(route('admin.courses.curriculum.reorder', $course), [
            'modules' => [
                ['id' => $moduleB->id, 'sort_order' => 0],
                ['id' => $moduleA->id, 'sort_order' => 1],
            ],
            'lessons' => [
                ['id' => $lessonA2->id, 'sort_order' => 0, 'course_module_id' => $moduleA->id],
                ['id' => $lessonA1->id, 'sort_order' => 1, 'course_module_id' => $moduleA->id],
            ],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(0, $moduleB->fresh()->sort_order);
        $this->assertSame(1, $moduleA->fresh()->sort_order);
        $this->assertSame(0, $lessonA2->fresh()->sort_order);
        $this->assertSame(1, $lessonA1->fresh()->sort_order);
    }

    public function test_reordering_rejects_ids_belonging_to_a_different_course(): void
    {
        $admin = $this->admin();
        [, , $lesson] = $this->moduleWithLesson();
        $otherCourse = Course::factory()->create();
        $foreignModule = CourseModule::create(['course_id' => $otherCourse->id, 'title' => 'Foreign', 'sort_order' => 0]);
        $originalModuleId = $lesson->course_module_id;

        $this->actingAs($admin)->postJson(route('admin.courses.curriculum.reorder', $otherCourse), [
            'lessons' => [['id' => $lesson->id, 'sort_order' => 0, 'course_module_id' => $foreignModule->id]],
        ])->assertOk();

        $this->assertSame($originalModuleId, $lesson->fresh()->course_module_id);
    }

    public function test_a_non_admin_cannot_toggle_publish_quick_add_or_reorder(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        [$course, $module, $lesson] = $this->moduleWithLesson();

        $this->actingAs($student)->post(route('admin.lessons.toggle-publish', $lesson))->assertRedirect(route('login'));
        $this->actingAs($student)->post(route('admin.modules.lessons.quick-store', $module), ['title' => 'X'])->assertRedirect(route('login'));
        $this->actingAs($student)->postJson(route('admin.courses.curriculum.reorder', $course), [])->assertRedirect(route('login'));
    }

    public function test_duration_fetch_reports_unavailable_with_no_api_key_configured(): void
    {
        config(['services.youtube.key' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.lessons.fetch-video-duration'), [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertOk()->assertJson(['available' => false, 'reason' => 'unavailable']);
    }

    public function test_duration_fetch_reports_not_youtube_for_a_non_youtube_url(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.lessons.fetch-video-duration'), [
            'video_url' => 'https://vimeo.com/12345',
        ])->assertOk()->assertJson(['available' => false, 'reason' => 'not_youtube']);
    }

    public function test_duration_fetch_parses_a_successful_api_response(): void
    {
        config(['services.youtube.key' => 'fake-key']);
        Http::fake([
            'googleapis.com/*' => Http::response([
                'items' => [['contentDetails' => ['duration' => 'PT15M33S']]],
            ]),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.lessons.fetch-video-duration'), [
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])->assertOk()->assertJson(['available' => true, 'minutes' => 16]);
    }
}
