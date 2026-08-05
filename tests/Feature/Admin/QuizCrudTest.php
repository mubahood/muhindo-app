<?php

namespace Tests\Feature\Admin;

use App\Enums\QuizFeedbackMode;
use App\Enums\QuizGradingMethod;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin CRUD for quiz settings (question management is a separate item). */
class QuizCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_an_admin_can_create_a_course_final_quiz(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();

        $this->actingAs($admin)->post(route('admin.courses.quizzes.store', $course), [
            'title' => 'Final Exam', 'pass_percent' => 70,
            'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ])->assertRedirect(route('admin.courses.show', $course));

        $quiz = Quiz::where('title', 'Final Exam')->first();
        $this->assertNotNull($quiz);
        $this->assertNull($quiz->lesson_id);
        $this->assertSame($course->id, $quiz->course_id);
        $this->assertSame(QuizGradingMethod::Highest, $quiz->grading_method);
        $this->assertSame(QuizFeedbackMode::AfterSubmit, $quiz->feedback_mode);
    }

    public function test_an_admin_can_create_a_lesson_quiz(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'sort_order' => 0]);
        $lesson = Lesson::create(['course_module_id' => $module->id, 'title' => 'L1', 'sort_order' => 0]);

        $this->actingAs($admin)->post(route('admin.courses.quizzes.store', $course), [
            'title' => 'Lesson Quiz', 'lesson_id' => $lesson->id, 'pass_percent' => 70,
            'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ]);

        $this->assertSame($lesson->id, Quiz::where('title', 'Lesson Quiz')->first()->lesson_id);
    }

    public function test_an_admin_can_update_quiz_settings(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create([
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ]);

        $this->actingAs($admin)->put(route('admin.quizzes.update', $quiz), [
            'title' => 'Quiz', 'pass_percent' => 80, 'max_attempts' => 3,
            'grading_method' => 'average', 'feedback_mode' => 'immediate',
            'is_published' => '1', 'shuffle_questions' => '1',
        ])->assertRedirect(route('admin.quizzes.edit', $quiz));

        $quiz->refresh();
        $this->assertSame(80, $quiz->pass_percent);
        $this->assertSame(3, $quiz->max_attempts);
        $this->assertSame(QuizGradingMethod::Average, $quiz->grading_method);
        $this->assertTrue($quiz->is_published);
        $this->assertTrue($quiz->shuffle_questions);
    }

    public function test_an_admin_can_delete_a_quiz_and_it_is_soft_deleted(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create([
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ]);

        $this->actingAs($admin)->delete(route('admin.quizzes.destroy', $quiz))
            ->assertRedirect(route('admin.courses.show', $course));

        $this->assertSoftDeleted('quizzes', ['id' => $quiz->id]);
    }

    public function test_a_non_admin_cannot_create_a_quiz(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();

        $this->actingAs($student)->post(route('admin.courses.quizzes.store', $course), [
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('quizzes', ['title' => 'Quiz']);
    }

    public function test_the_course_page_lists_its_quizzes(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $course->quizzes()->create([
            'title' => 'Midterm', 'pass_percent' => 70, 'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ]);

        $this->actingAs($admin)->get(route('admin.courses.show', $course))->assertOk()->assertSee('Midterm');
    }
}
