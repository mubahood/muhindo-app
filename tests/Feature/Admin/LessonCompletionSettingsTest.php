<?php

namespace Tests\Feature\Admin;

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use App\Enums\CourseProgression;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §4.3/§7.4 — admin can configure per-lesson completion rules and content format, per-course progression. */
class LessonCompletionSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_a_new_lesson_defaults_to_manual_completion_and_plain_content(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'Intro', 'sort_order' => 0,
        ])->assertRedirect();

        $lesson = Lesson::where('title', 'Intro')->first();
        $this->assertSame(CompletionRule::Manual, $lesson->completion_rule);
        $this->assertSame(ContentFormat::Plain, $lesson->content_format);
        $this->assertSame(80, $lesson->completion_threshold);
    }

    public function test_an_admin_can_set_a_lesson_to_min_watch_with_a_custom_threshold(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'Watch this', 'sort_order' => 0,
            'completion_rule' => 'min_watch', 'completion_threshold' => 90,
        ]);

        $lesson = Lesson::where('title', 'Watch this')->first();
        $this->assertSame(CompletionRule::MinWatch, $lesson->completion_rule);
        $this->assertSame(90, $lesson->completion_threshold);
    }

    public function test_a_not_yet_enforced_completion_rule_is_rejected_even_if_submitted_directly(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'Sneaky', 'sort_order' => 0, 'completion_rule' => 'quiz_pass',
        ])->assertSessionHasErrors('completion_rule');

        $this->assertDatabaseMissing('lessons', ['title' => 'Sneaky']);
    }

    public function test_an_admin_can_set_a_lessons_content_format_to_markdown(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);

        $this->actingAs($admin)->post(route('admin.modules.lessons.store', $module), [
            'title' => 'MD Lesson', 'sort_order' => 0, 'content_format' => 'markdown', 'content' => '# Hi',
        ]);

        $this->assertSame(ContentFormat::Markdown, Lesson::where('title', 'MD Lesson')->first()->content_format);
    }

    public function test_a_new_course_defaults_to_free_progression(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'New Course', 'price' => 0, 'level' => 'beginner',
        ]);

        $this->assertSame(CourseProgression::Free, Course::where('title', 'New Course')->first()->progression);
    }

    public function test_an_admin_can_set_a_course_to_sequential_progression(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.courses.store'), [
            'title' => 'Sequential Course', 'price' => 0, 'level' => 'beginner', 'progression' => 'sequential',
        ]);

        $this->assertSame(CourseProgression::Sequential, Course::where('title', 'Sequential Course')->first()->progression);
    }
}
