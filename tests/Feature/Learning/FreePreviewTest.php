<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A guest (no enrollment, no login) can view a free-preview lesson, closing L5. */
class FreePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_view_a_free_preview_lesson(): void
    {
        $course = Course::factory()->create(['is_published' => true, 'title' => 'Laravel Basics']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create([
            'course_module_id' => $module->id, 'title' => 'Intro', 'sort_order' => 0,
            'is_free_preview' => true, 'content' => 'Welcome to the course!',
        ]);

        $this->get(route('courses.preview', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Intro')
            ->assertSee('Welcome to the course!')
            ->assertSee('Enrol');
    }

    public function test_a_guest_cannot_view_a_lesson_that_is_not_marked_free_preview(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Locked', 'sort_order' => 0, 'is_free_preview' => false]);

        $this->get(route('courses.preview', [$course, $lesson]))->assertNotFound();
    }

    public function test_a_free_preview_lesson_on_an_unpublished_course_is_not_viewable(): void
    {
        $course = Course::factory()->create(['is_published' => false]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Intro', 'sort_order' => 0, 'is_free_preview' => true]);

        $this->get(route('courses.preview', [$course, $lesson]))->assertNotFound();
    }

    public function test_the_free_preview_tag_on_the_course_page_links_to_the_preview(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Intro', 'sort_order' => 0, 'is_free_preview' => true]);

        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertSee(route('courses.preview', [$course, $lesson]), false);
    }

    public function test_a_lesson_belonging_to_a_different_course_404s(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $otherCourse = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $otherCourse->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'Intro', 'sort_order' => 0, 'is_free_preview' => true]);

        $this->get(route('courses.preview', [$course, $lesson]))->assertNotFound();
    }
}
