<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** L7, restructuring a course must never destroy student progress history. */
class ContentSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function courseWithTwoLessons(): array
    {
        $course = Course::factory()->create(['is_published' => true]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lessonOne = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);
        $lessonTwo = Lesson::create(['course_module_id' => $module->id, 'title' => 'L2', 'sort_order' => 1]);

        return [$course, $module, $lessonOne, $lessonTwo];
    }

    private function activeEnrollment(User $student, Course $course): Enrollment
    {
        return Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    public function test_deleting_a_lesson_soft_deletes_it_instead_of_removing_the_row(): void
    {
        [, , $lesson] = $this->courseWithTwoLessons();

        $lesson->delete();

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
    }

    public function test_deleting_a_module_soft_deletes_it_instead_of_removing_the_row(): void
    {
        [, $module] = $this->courseWithTwoLessons();

        $module->delete();

        $this->assertSoftDeleted('course_modules', ['id' => $module->id]);
    }

    public function test_deleting_a_lesson_preserves_the_students_progress_row(): void
    {
        [$course, , $lesson] = $this->courseWithTwoLessons();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->activeEnrollment($student, $course);
        $enrollment->progressRecords()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);

        $lesson->delete();

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);
        $this->assertNotNull(LessonProgress::first()->completed_at);
    }

    public function test_deleting_a_module_preserves_progress_for_its_lessons(): void
    {
        [$course, $module, $lesson] = $this->courseWithTwoLessons();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->activeEnrollment($student, $course);
        $enrollment->progressRecords()->create(['lesson_id' => $lesson->id, 'completed_at' => now()]);

        $module->delete();

        $this->assertDatabaseHas('lesson_progress', [
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_a_completed_enrollments_certificate_survives_deleting_a_lesson_afterwards(): void
    {
        [$course, , $lessonOne, $lessonTwo] = $this->courseWithTwoLessons();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->activeEnrollment($student, $course);
        $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);
        $enrollment->progressRecords()->create(['lesson_id' => $lessonTwo->id, 'completed_at' => now()]);
        $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

        // Instructor later removes a lesson from the (now finished) course.
        $lessonTwo->delete();
        $enrollment->refresh();

        $this->assertSame('completed', $enrollment->status);
        $this->assertNotNull($enrollment->completed_at);
    }

    public function test_lesson_count_and_progress_percent_exclude_a_deleted_lesson(): void
    {
        [$course, , $lessonOne, $lessonTwo] = $this->courseWithTwoLessons();
        $student = User::factory()->create(['role' => 'student']);
        $enrollment = $this->activeEnrollment($student, $course);
        $enrollment->progressRecords()->create(['lesson_id' => $lessonOne->id, 'completed_at' => now()]);

        $this->assertSame(2, $course->fresh()->lessonCount());
        $this->assertSame(50, $enrollment->progressPercent());

        $lessonTwo->delete();

        $this->assertSame(1, $course->fresh()->lessonCount());
        $this->assertSame(100, $enrollment->progressPercent());
    }

    public function test_lesson_count_excludes_lessons_of_a_deleted_module(): void
    {
        [$course, $module] = $this->courseWithTwoLessons();

        $module->delete();

        $this->assertSame(0, $course->fresh()->lessonCount());
    }

    public function test_a_deleted_lesson_is_no_longer_reachable_in_the_player(): void
    {
        [$course, , $lesson] = $this->courseWithTwoLessons();
        $student = User::factory()->create(['role' => 'student']);
        $this->activeEnrollment($student, $course);
        $lesson->delete();

        $this->actingAs($student)->get(route('learn.lesson', [$course, $lesson]))->assertNotFound();
    }

    public function test_admin_can_delete_a_module_via_the_destroy_route_and_it_is_soft_deleted(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        [$course, $module] = $this->courseWithTwoLessons();
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        $this->actingAs($admin)->delete(route('admin.modules.destroy', $module))->assertRedirect();

        $this->assertSoftDeleted('course_modules', ['id' => $module->id]);
    }
}
