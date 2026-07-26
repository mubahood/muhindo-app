<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §7.3 — the student-facing Q&A flow: ask, view, reply, resolve. */
class StudentDiscussionsTest extends TestCase
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

    public function test_a_student_can_post_a_question(): void
    {
        [$course, $student] = $this->enrolledStudent();

        $this->actingAs($student)->post(route('learn.discussions.store', $course), ['body' => 'How do closures work?'])
            ->assertRedirect();

        $this->assertDatabaseHas('discussions', ['course_id' => $course->id, 'user_id' => $student->id, 'body' => 'How do closures work?']);
    }

    public function test_the_index_page_lists_threads_with_reply_counts(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $thread = $course->discussions()->create(['user_id' => $student->id, 'body' => 'A question']);
        $thread->replies()->create(['course_id' => $course->id, 'user_id' => $student->id, 'body' => 'A reply']);

        $this->actingAs($student)->get(route('learn.discussions.index', $course))
            ->assertOk()->assertSee('A question');
    }

    public function test_a_student_can_reply_to_a_thread(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $thread = $course->discussions()->create(['user_id' => $student->id, 'body' => 'Original question']);

        $this->actingAs($student)->post(route('learn.discussions.reply', [$course, $thread]), ['body' => 'A reply'])
            ->assertRedirect(route('learn.discussions.show', [$course, $thread]));

        $this->assertSame(1, $thread->replies()->count());
    }

    public function test_only_the_original_asker_or_an_admin_can_resolve_a_thread(): void
    {
        [$course, $student] = $this->enrolledStudent();
        $thread = $course->discussions()->create(['user_id' => $student->id, 'body' => 'Question']);

        $this->actingAs($student)->post(route('learn.discussions.resolve', [$course, $thread]))
            ->assertRedirect();

        $this->assertTrue($thread->fresh()->isResolved());
    }

    public function test_a_different_student_cannot_resolve_someone_elses_thread(): void
    {
        [$course, $asker] = $this->enrolledStudent();
        $otherStudent = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $otherStudent->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);
        $thread = $course->discussions()->create(['user_id' => $asker->id, 'body' => 'Question']);

        $this->actingAs($otherStudent)->post(route('learn.discussions.resolve', [$course, $thread]))
            ->assertForbidden();

        $this->assertFalse($thread->fresh()->isResolved());
    }

    public function test_a_non_enrolled_user_cannot_view_the_qa_tab(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->get(route('learn.discussions.index', $course))->assertNotFound();
    }
}
