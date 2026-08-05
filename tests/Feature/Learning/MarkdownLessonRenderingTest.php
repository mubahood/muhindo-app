<?php

namespace Tests\Feature\Learning;

use App\Enums\ContentFormat;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Content_format=markdown renders sanitized HTML; plain lessons render exactly as before. */
class MarkdownLessonRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentForLesson(array $lessonAttributes): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(array_merge(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0], $lessonAttributes));
        $student = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student];
    }

    public function test_a_markdown_lesson_renders_sanitized_html(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([
            'content' => "# Heading\n\nSome **bold** text.",
            'content_format' => ContentFormat::Markdown->value,
        ]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('<h1>Heading</h1>', false)
            ->assertSee('<strong>bold</strong>', false);
    }

    public function test_a_plain_lesson_still_renders_via_nl2br(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([
            'content' => "Line one\nLine two",
            'content_format' => ContentFormat::Plain->value,
        ]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Line one<br', false)
            ->assertDontSee('class="card markdown-body"', false);
    }

    public function test_markdown_content_with_a_script_tag_is_not_executable(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentForLesson([
            'content' => 'Hello <script>alert(1)</script>',
            'content_format' => ContentFormat::Markdown->value,
        ]);

        $response = $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]));

        $response->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }
}
