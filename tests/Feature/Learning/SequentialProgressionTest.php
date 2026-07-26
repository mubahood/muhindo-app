<?php

namespace Tests\Feature\Learning;

use App\Enums\CourseProgression;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** §4.3 — in sequential progression, lesson N+1 is locked until N is complete, server-side. */
class SequentialProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function enrollmentInCourse(CourseProgression $progression): array
    {
        $course = Course::factory()->create(['progression' => $progression]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);
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

    public function test_the_first_lesson_is_never_locked_in_a_sequential_course(): void
    {
        [$course, $lessonOne, , $student] = $this->enrollmentInCourse(CourseProgression::Sequential);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonOne]))->assertOk();
    }

    public function test_the_second_lesson_is_locked_until_the_first_is_completed(): void
    {
        [$course, , $lessonTwo, $student] = $this->enrollmentInCourse(CourseProgression::Sequential);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonTwo]))->assertForbidden();
    }

    public function test_a_direct_complete_post_on_a_locked_lesson_is_rejected_and_writes_no_progress(): void
    {
        [$course, , $lessonTwo, $student, $enrollment] = $this->enrollmentInCourse(CourseProgression::Sequential);

        $this->actingAs($student)->post(route('learn.lesson.complete', [$course, $lessonTwo]))->assertForbidden();

        $this->assertDatabaseMissing('lesson_progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $lessonTwo->id]);
    }

    public function test_the_second_lesson_unlocks_once_the_first_is_completed(): void
    {
        [$course, $lessonOne, $lessonTwo, $student, $enrollment] = $this->enrollmentInCourse(CourseProgression::Sequential);
        $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonTwo]))->assertOk();
    }

    public function test_a_locked_lessons_materials_cannot_be_downloaded(): void
    {
        [$course, , $lessonTwo, $student] = $this->enrollmentInCourse(CourseProgression::Sequential);
        $material = LessonMaterial::create(['lesson_id' => $lessonTwo->id, 'title' => 'Slides', 'type' => 'pdf', 'file_path' => 'x.pdf']);

        $this->actingAs($student)->get(route('learn.materials.download', [$course, $lessonTwo, $material]))->assertForbidden();
    }

    public function test_a_free_progression_course_never_locks_any_lesson(): void
    {
        [$course, , $lessonTwo, $student] = $this->enrollmentInCourse(CourseProgression::Free);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonTwo]))->assertOk();
    }

    public function test_the_locked_lesson_is_rendered_as_a_padlock_not_a_link_in_the_sidebar(): void
    {
        [$course, $lessonOne, , $student] = $this->enrollmentInCourse(CourseProgression::Sequential);

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonOne]))
            ->assertOk()
            ->assertSee('fa-lock', false)
            ->assertDontSee(route('learn.lesson', [$course, $course->fresh()->modules->first()->lessons->last()]));
    }

    public function test_resuming_a_now_locked_lesson_falls_back_to_the_first_lesson_instead_of_403ing(): void
    {
        [$course, $lessonOne, $lessonTwo, $student, $enrollment] = $this->enrollmentInCourse(CourseProgression::Free);
        // The student viewed lesson 2 while the course was still free-navigation...
        $this->actingAs($student)->get(route('learn.lesson', [$course, $lessonTwo]))->assertOk();
        // ...then the instructor switches the course to sequential, leaving a stale last_lesson_id.
        $course->update(['progression' => CourseProgression::Sequential]);

        $this->get(route('learn.course', $course))
            ->assertRedirect(route('learn.lesson', [$course, $lessonOne]));
    }
}
