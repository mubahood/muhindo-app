<?php

namespace Tests\Feature\Admin;

use App\Enums\QuestionType;
use App\Models\Course;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin question/option builder, one form adapting to each of the 9 question types. */
class QuestionBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    private function quiz(): Quiz
    {
        $course = Course::factory()->create();

        return $course->quizzes()->create([
            'title' => 'Quiz', 'pass_percent' => 70, 'grading_method' => 'highest', 'feedback_mode' => 'after_submit',
        ]);
    }

    public function test_an_admin_can_create_an_mcq_single_question_with_options(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'mcq_single', 'prompt' => 'What is 2+2?', 'points' => 1,
            'options' => [
                ['label' => '3', 'is_correct' => '0'],
                ['label' => '4', 'is_correct' => '1'],
            ],
        ])->assertRedirect(route('admin.quizzes.edit', $quiz));

        $question = Question::where('prompt', 'What is 2+2?')->first();
        $this->assertSame(QuestionType::McqSingle, $question->type);
        $this->assertSame(2, $question->options->count());
        $this->assertSame('4', $question->options->firstWhere('is_correct', true)->label);
    }

    public function test_an_admin_can_create_a_fill_blank_question_with_accepted_answers(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'fill_blank', 'prompt' => 'The capital of France is ___.', 'points' => 1,
            'accepted_answers' => "Paris\nparis",
        ]);

        $question = Question::where('prompt', 'The capital of France is ___.')->first();
        $this->assertSame(['Paris', 'paris'], $question->meta['accepted_answers']);
        $this->assertSame(0, $question->options->count());
    }

    public function test_an_admin_can_create_a_numeric_question_with_tolerance(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'numeric', 'prompt' => 'What is pi to 2dp?', 'points' => 1,
            'numeric_expected' => 3.14, 'numeric_tolerance' => 0.01,
        ]);

        $question = Question::where('prompt', 'What is pi to 2dp?')->first();
        $this->assertSame(3.14, $question->meta['expected']);
        $this->assertSame(0.01, $question->meta['tolerance']);
    }

    public function test_an_admin_can_create_a_matching_question_with_match_keys(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'matching', 'prompt' => 'Match the capitals', 'points' => 2,
            'options' => [
                ['label' => 'France', 'match_key' => 'Paris'],
                ['label' => 'Uganda', 'match_key' => 'Kampala'],
            ],
        ]);

        $question = Question::where('prompt', 'Match the capitals')->first();
        $this->assertSame('Paris', $question->options->firstWhere('label', 'France')->match_key);
    }

    public function test_an_admin_can_create_an_essay_question_with_no_grading_config(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();

        $this->actingAs($admin)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'essay', 'prompt' => 'Discuss the causes of X.', 'points' => 10,
        ]);

        $question = Question::where('prompt', 'Discuss the causes of X.')->first();
        $this->assertSame(QuestionType::Essay, $question->type);
        $this->assertNull($question->meta);
        $this->assertSame(0, $question->options->count());
    }

    public function test_updating_a_question_replaces_its_options(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'Q', 'points' => 1, 'sort_order' => 0]);
        $question->options()->create(['label' => 'Old', 'is_correct' => true, 'sort_order' => 0]);

        $this->actingAs($admin)->put(route('admin.questions.update', $question), [
            'type' => 'mcq_single', 'prompt' => 'Q', 'points' => 1,
            'options' => [['label' => 'New', 'is_correct' => '1']],
        ]);

        $question->refresh();
        $this->assertSame(1, $question->options->count());
        $this->assertSame('New', $question->options->first()->label);
    }

    public function test_deleting_a_question_is_soft_deleted_and_preserves_the_quiz(): void
    {
        $admin = $this->admin();
        $quiz = $this->quiz();
        $question = $quiz->questions()->create(['type' => 'essay', 'prompt' => 'Q', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($admin)->delete(route('admin.questions.destroy', $question))
            ->assertRedirect(route('admin.quizzes.edit', $quiz));

        $this->assertSoftDeleted('questions', ['id' => $question->id]);
        $this->assertNotSoftDeleted('quizzes', ['id' => $quiz->id]);
    }

    public function test_a_non_admin_cannot_create_a_question(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $quiz = $this->quiz();

        $this->actingAs($student)->post(route('admin.quizzes.questions.store', $quiz), [
            'type' => 'essay', 'prompt' => 'Q', 'points' => 1,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('questions', ['prompt' => 'Q']);
    }
}
