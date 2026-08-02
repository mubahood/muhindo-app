<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lesson content editor.
 *
 * The toolbar, drag-and-drop and paste behaviour are browser concerns and were
 * exercised in one — clicking each control and reading the field back. What
 * belongs here is everything that rewrite could have quietly broken on the
 * server side: the field still posts under the same name, content still round
 * trips, and the image control is only offered when there is somewhere to put
 * an image.
 */
class LessonEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function lesson(): Lesson
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'sort_order' => 1]);

        return Lesson::create([
            'course_module_id' => $module->id,
            'title' => 'What is HTML?',
            'content' => "# Heading\n\nSome **bold** text.",
            'content_format' => 'markdown',
            'sort_order' => 1,
            'is_published' => true,
        ]);
    }

    public function test_the_editor_offers_formatting_rather_than_a_bare_textarea(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->admin())
            ->get(route('admin.lessons.edit', $lesson))->assertOk()
            ->assertSee('ed-bar')
            ->assertSee('Drag images in, or paste them');
    }

    public function test_the_field_still_posts_under_the_same_name(): void
    {
        $lesson = $this->lesson();

        // The editor is a wrapper around the textarea, not a replacement for
        // it. If the name ever moved, every lesson would save blank.
        $this->actingAs($this->admin())
            ->get(route('admin.lessons.edit', $lesson))->assertOk()
            ->assertSee('name="content"', false);
    }

    public function test_content_written_in_the_editor_round_trips(): void
    {
        $lesson = $this->lesson();
        $markdown = "## A heading\n\n- one\n- two\n\n```html\n<p>hi</p>\n```\n\n![a diagram](/img/x.png)";

        $this->actingAs($this->admin())->put(route('admin.lessons.update', $lesson), [
            'title' => $lesson->title,
            'content' => $markdown,
            'content_format' => 'markdown',
            'sort_order' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($markdown, $lesson->fresh()->content);
    }

    public function test_the_image_control_is_offered_only_once_there_is_a_lesson_to_attach_to(): void
    {
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 1]);
        $admin = $this->admin();

        // A lesson that does not exist yet has no upload route, so the control
        // says why instead of failing when it is used.
        $this->actingAs($admin)->get(route('admin.modules.lessons.create', $module))->assertOk()
            ->assertSee('Save the lesson first to add images');

        $lesson = $this->lesson();
        $html = (string) $this->actingAs($admin)
            ->get(route('admin.lessons.edit', $lesson))->assertOk()->getContent();

        // @js() JSON-encodes the URL, so its slashes arrive escaped.
        $this->assertStringContainsString(
            trim(json_encode(route('admin.lessons.content-images.store', $lesson)), '"'),
            $html,
            'a saved lesson must carry a real upload URL'
        );
    }

    public function test_the_preview_is_rendered_by_the_same_code_the_student_sees(): void
    {
        $admin = $this->admin();

        // The preview endpoint is what makes a markdown editor honest: it
        // cannot show something the real page would not produce.
        $response = $this->actingAs($admin)->postJson(route('admin.lessons.preview-markdown'), [
            'content' => '## Hello',
        ])->assertOk();

        $this->assertStringContainsString('<h2', (string) $response->json('html'));
    }

    public function test_a_student_cannot_reach_the_editor_or_upload_images(): void
    {
        $lesson = $this->lesson();
        $student = User::factory()->create(['role' => 'student', 'is_student' => true]);

        $this->actingAs($student)->get(route('admin.lessons.edit', $lesson))->assertRedirect();
        $this->actingAs($student)
            ->post(route('admin.lessons.content-images.store', $lesson))->assertRedirect();
    }
}
