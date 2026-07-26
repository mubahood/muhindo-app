<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §7.3 — private, timestamped lesson notes: create, list, delete, and the click-to-seek timestamp. */
class LessonNotesTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledStudentWithLesson(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $student->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        return [$course, $lesson, $student, $enrollment];
    }

    public function test_a_student_can_add_a_timestamped_note(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithLesson();

        $this->actingAs($student)->post(route('learn.notes.store', [$course, $lesson]), [
            'body' => 'Remember this part', 'seconds' => 125,
        ])->assertRedirect();

        $note = LessonNote::first();
        $this->assertSame($enrollment->id, $note->enrollment_id);
        $this->assertSame(125, $note->seconds);
        $this->assertSame('2:05', $note->formattedTime());
    }

    public function test_a_note_without_a_timestamp_is_allowed(): void
    {
        [$course, $lesson, $student] = $this->enrolledStudentWithLesson();

        $this->actingAs($student)->post(route('learn.notes.store', [$course, $lesson]), ['body' => 'General thought']);

        $note = LessonNote::first();
        $this->assertNull($note->seconds);
        $this->assertNull($note->formattedTime());
    }

    public function test_the_lesson_page_shows_the_students_notes(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithLesson();
        $enrollment->lessonNotes()->create(['lesson_id' => $lesson->id, 'body' => 'My saved note', 'seconds' => 42]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))
            ->assertOk()->assertSee('My saved note')->assertSee('0:42');
    }

    public function test_a_student_can_delete_their_own_note(): void
    {
        [$course, $lesson, $student, $enrollment] = $this->enrolledStudentWithLesson();
        $note = $enrollment->lessonNotes()->create(['lesson_id' => $lesson->id, 'body' => 'Delete me']);

        $this->actingAs($student)->delete(route('learn.notes.destroy', [$course, $lesson, $note]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lesson_notes', ['id' => $note->id]);
    }

    public function test_a_student_cannot_delete_another_students_note(): void
    {
        [$course, $lesson, , $enrollment] = $this->enrolledStudentWithLesson();
        $note = $enrollment->lessonNotes()->create(['lesson_id' => $lesson->id, 'body' => 'Not yours']);

        $intruder = User::factory()->create(['role' => 'student']);
        Enrollment::create([
            'uuid' => (string) Str::uuid(), 'user_id' => $intruder->id, 'course_id' => $course->id,
            'status' => 'active', 'source' => 'self', 'enrolled_at' => now(),
        ]);

        $this->actingAs($intruder)->delete(route('learn.notes.destroy', [$course, $lesson, $note]))
            ->assertNotFound();

        $this->assertDatabaseHas('lesson_notes', ['id' => $note->id]);
    }

    public function test_a_non_enrolled_user_cannot_add_a_note(): void
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $stranger = User::factory()->create(['role' => 'student']);

        $this->actingAs($stranger)->post(route('learn.notes.store', [$course, $lesson]), ['body' => 'x'])
            ->assertNotFound();
    }
}
