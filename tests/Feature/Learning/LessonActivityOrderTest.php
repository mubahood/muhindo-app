<?php

namespace Tests\Feature\Learning;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Learning\ProgressService;
use App\Support\Learning\LearnShell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Quizzes and tasks belong to the topic they hang off.
 *
 * Two claims: a topic cannot be finished while its work is outstanding, and
 * that work appears in the curriculum next to the topic rather than only in a
 * separate list a student never opens. A topic may carry none, one, or several.
 */
class LessonActivityOrderTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

    private CourseModule $module;

    private User $student;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student', 'is_student' => true]);
        $this->course = Course::factory()->create(['is_published' => true, 'price' => 0, 'progression' => 'free']);
        $this->module = CourseModule::create(['course_id' => $this->course->id, 'title' => 'Module 1', 'sort_order' => 1]);

        $this->enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);
    }

    private function lesson(string $title = 'A topic', int $order = 1): Lesson
    {
        return Lesson::create([
            'course_module_id' => $this->module->id,
            'title' => $title,
            'content' => 'Read this.',
            'sort_order' => $order,
            'is_published' => true,
        ]);
    }

    private function quiz(Lesson $lesson, string $title, bool $required = true): Quiz
    {
        return Quiz::create([
            'course_id' => $this->course->id,
            'lesson_id' => $lesson->id,
            'title' => $title,
            'is_published' => true,
            'is_required' => $required,
            'pass_percent' => 50,
        ]);
    }

    private function task(Lesson $lesson, string $title, bool $required = true): Assignment
    {
        return Assignment::create([
            'course_id' => $this->course->id,
            'lesson_id' => $lesson->id,
            'title' => $title,
            'instructions' => 'Do the thing.',
            'is_published' => true,
            'is_required' => $required,
            'points' => 10,
        ]);
    }

    private function blockers(Lesson $lesson): array
    {
        return app(ProgressService::class)->completionBlockers($this->enrollment, $lesson);
    }

    // A topic cannot be finished with its work outstanding

    public function test_a_topic_with_no_work_is_free_to_complete(): void
    {
        // "or none" the common case must stay frictionless.
        $this->assertSame([], $this->blockers($this->lesson()));
    }

    public function test_an_attached_quiz_blocks_the_topic(): void
    {
        $lesson = $this->lesson();
        $this->quiz($lesson, 'Check your understanding');

        $blockers = $this->blockers($lesson);
        $this->assertCount(1, $blockers);
        $this->assertSame('quiz', $blockers[0]['type']);
        $this->assertStringContainsString('Check your understanding', $blockers[0]['message']);
    }

    public function test_an_attached_task_blocks_the_topic(): void
    {
        $lesson = $this->lesson();
        $this->task($lesson, 'Build a page');

        $blockers = $this->blockers($lesson);
        $this->assertCount(1, $blockers);
        $this->assertSame('assignment', $blockers[0]['type']);
    }

    public function test_every_piece_of_work_must_be_done_not_just_the_first(): void
    {
        $lesson = $this->lesson();
        $one = $this->quiz($lesson, 'Quiz one');
        $this->quiz($lesson, 'Quiz two');
        $this->task($lesson, 'The task');

        $this->assertCount(3, $this->blockers($lesson));

        // Submitting one leaves the other two standing.
        $this->submit($one);
        $this->assertCount(2, $this->blockers($lesson));
    }

    public function test_the_topic_opens_up_once_everything_is_submitted(): void
    {
        $lesson = $this->lesson();
        $quiz = $this->quiz($lesson, 'Quiz');
        $task = $this->task($lesson, 'Task');

        $this->submit($quiz);
        $this->submitTask($task);

        $this->assertSame([], $this->blockers($lesson));
    }

    public function test_work_the_author_marked_optional_still_does_not_block(): void
    {
        $lesson = $this->lesson();
        $this->quiz($lesson, 'Practice quiz', required: false);

        // The default flipped to compulsory, but an author can still say a
        // quiz is practice. That capability was worth keeping.
        $this->assertSame([], $this->blockers($lesson));
    }

    public function test_a_draft_quiz_does_not_block_a_student_who_cannot_see_it(): void
    {
        $lesson = $this->lesson();
        $quiz = $this->quiz($lesson, 'Not ready yet');
        $quiz->update(['is_published' => false]);

        $this->assertSame([], $this->blockers($lesson));
    }

    public function test_work_on_another_topic_does_not_block_this_one(): void
    {
        $first = $this->lesson('First topic', 1);
        $second = $this->lesson('Second topic', 2);
        $this->quiz($second, 'Second topic quiz');

        $this->assertSame([], $this->blockers($first));
        $this->assertCount(1, $this->blockers($second));
    }

    public function test_completing_the_lesson_over_http_is_refused_while_work_is_outstanding(): void
    {
        $lesson = $this->lesson();
        $this->quiz($lesson, 'Check your understanding');

        $this->actingAs($this->student)
            ->post(route('learn.lesson.complete', [$this->course, $lesson]));

        $this->assertNull(
            \App\Models\LessonProgress::where('lesson_id', $lesson->id)->value('completed_at'),
            'the attached quiz was never submitted, so this must not be complete'
        );
    }

    // Where the work appears

    public function test_the_curriculum_lists_a_topics_work_beneath_it_in_order(): void
    {
        $lesson = $this->lesson();
        $this->quiz($lesson, 'The quiz');
        $this->task($lesson, 'The task');

        $shell = new LearnShell($this->course, $this->student, $lesson);
        $activities = $shell->activitiesFor($lesson);

        // Questions before work, the order a student meets them.
        $this->assertSame(['quiz', 'task'], array_column($activities, 'type'));
        $this->assertSame(['The quiz', 'The task'], array_column($activities, 'title'));
        $this->assertSame([false, false], array_column($activities, 'done'));
    }

    public function test_the_curriculum_marks_work_as_done_once_it_is_submitted(): void
    {
        $lesson = $this->lesson();
        $quiz = $this->quiz($lesson, 'The quiz');
        $this->submit($quiz);

        $shell = new LearnShell($this->course, $this->student, $lesson);
        $this->assertTrue($shell->activitiesFor($lesson)[0]['done']);
    }

    public function test_a_topic_with_no_work_lists_none(): void
    {
        $lesson = $this->lesson();

        $shell = new LearnShell($this->course, $this->student, $lesson);
        $this->assertSame([], $shell->activitiesFor($lesson));
    }

    public function test_the_sidebar_shows_the_work_next_to_its_topic(): void
    {
        $lesson = $this->lesson();
        $this->quiz($lesson, 'Sidebar quiz');
        $this->task($lesson, 'Sidebar task');

        $this->actingAs($this->student)
            ->get(route('learn.lesson', [$this->course, $lesson]))->assertOk()
            ->assertSee('Sidebar quiz')
            ->assertSee('Sidebar task')
            ->assertSee('act-link');
    }

    public function test_the_author_sees_the_same_structure_they_are_building(): void
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        $lesson = $this->lesson();
        $this->quiz($lesson, 'Author-visible quiz');
        $this->task($lesson, 'Author-visible task', required: false);

        $this->actingAs($admin)->get(route('admin.courses.show', $this->course))->assertOk()
            ->assertSee('Author-visible quiz')
            ->assertSee('Author-visible task')
            ->assertSee('Must be done to finish the topic')
            ->assertSee('Optional, does not block the topic');
    }

    // Helpers

    private function submit(Quiz $quiz): void
    {
        $this->enrollment->quizAttempts()->create([
            'uuid' => (string) Str::uuid(),
            'quiz_id' => $quiz->id,
            'attempt_no' => 1,
            'status' => 'submitted',
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
        ]);
    }

    private function submitTask(Assignment $assignment): void
    {
        $this->enrollment->assignmentSubmissions()->create([
            'uuid' => (string) Str::uuid(),
            'assignment_id' => $assignment->id,
            'attempt_no' => 1,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }
}
