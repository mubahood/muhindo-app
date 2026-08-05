<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** "resume where you left off" instead of always restarting at lesson #1. */
class ResumeUxTest extends TestCase
{
    use RefreshDatabase;

    private function activeEnrollmentWithTwoLessons(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
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

    public function test_a_never_visited_course_starts_at_the_first_lesson(): void
    {
        [$course, $lessonOne, , $student] = $this->activeEnrollmentWithTwoLessons();

        $this->actingAs($student)->get(route('learn.course', $course))
            ->assertRedirect(route('learn.lesson', [$course, $lessonOne]));
    }

    public function test_a_returning_student_resumes_at_their_last_viewed_lesson(): void
    {
        [$course, , $lessonTwo, $student, $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $this->actingAs($student);
        app(ProgressService::class)->recordView($enrollment, $lessonTwo);

        $this->get(route('learn.course', $course))
            ->assertRedirect(route('learn.lesson', [$course, $lessonTwo]));
    }

    public function test_a_stale_last_lesson_from_another_course_is_ignored(): void
    {
        [$course, $lessonOne, , $student, $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $otherCourse = Course::factory()->create(['is_published' => true]);
        $otherModule = CourseModule::create(['course_id' => $otherCourse->id, 'title' => 'OM', 'sort_order' => 0]);
        $otherLesson = Lesson::create(['course_module_id' => $otherModule->id, 'title' => 'OL', 'sort_order' => 0]);
        $enrollment->update(['last_lesson_id' => $otherLesson->id]);

        $this->actingAs($student)->get(route('learn.course', $course))
            ->assertRedirect(route('learn.lesson', [$course, $lessonOne]));
    }

    public function test_the_my_courses_card_shows_a_resume_hint_after_a_lesson_view(): void
    {
        [$course, , $lessonTwo, $student, $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $this->actingAs($student);
        app(ProgressService::class)->recordView($enrollment, $lessonTwo);

        $this->get(route('learn.index'))->assertSee('Resume at "L2"', false);
    }

    public function test_the_my_courses_card_shows_lessons_left(): void
    {
        [$course, $lessonOne, , $student, $enrollment] = $this->activeEnrollmentWithTwoLessons();
        $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);
        $enrollment->update(['progress_percent' => 50]);

        $this->actingAs($student)->get(route('learn.index'))->assertSee('1 lesson left');
    }
}
