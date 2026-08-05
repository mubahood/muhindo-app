<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** The student's Announcements tab shows only published announcements, newest first. */
class StudentAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudent(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return [$course, $student, $enrollment];
    }

    public function test_only_published_announcements_are_shown(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $course->announcements()->create(['title' => 'Published one', 'body' => 'Hello', 'published_at' => now()]);
        $course->announcements()->create(['title' => 'Still a draft', 'body' => 'Shh', 'published_at' => null]);

        $this->actingAs($student)->get(route('learn.announcements.index', $course))
            ->assertOk()->assertSee('Published one')->assertDontSee('Still a draft');
    }

    public function test_a_non_enrolled_user_cannot_view_announcements(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->get(route('learn.announcements.index', $course))->assertNotFound();
    }

    public function test_markdown_in_the_body_is_rendered(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $course->announcements()->create(['title' => 'Formatting', 'body' => '**bold text**', 'published_at' => now()]);

        $this->actingAs($student)->get(route('learn.announcements.index', $course))
            ->assertOk()->assertSee('<strong>bold text</strong>', false);
    }
}
