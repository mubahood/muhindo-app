<?php

namespace Tests\Feature\Admin;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** §6.3.4 — the admin-facing quiz item analysis page. */
class QuizAnalysisPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\RbacSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin', 'is_admin' => true]);
        $admin->syncSpatieRole();

        return $admin;
    }

    public function test_the_analysis_page_lists_every_question(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create(['title' => 'Midterm', 'pass_percent' => 70]);
        $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'What is 2+2?', 'points' => 1, 'sort_order' => 0]);

        $this->actingAs($admin)->get(route('admin.quizzes.analysis', $quiz))
            ->assertOk()->assertSee('What is 2+2?')->assertSee('No answers yet');
    }

    public function test_the_edit_quiz_page_links_to_item_analysis(): void
    {
        $admin = $this->admin();
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create(['title' => 'Midterm', 'pass_percent' => 70]);

        $this->actingAs($admin)->get(route('admin.quizzes.edit', $quiz))
            ->assertOk()->assertSee(route('admin.quizzes.analysis', $quiz));
    }

    public function test_a_non_admin_cannot_view_item_analysis(): void
    {
        $course = Course::factory()->create();
        $quiz = $course->quizzes()->create(['title' => 'Midterm', 'pass_percent' => 70]);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get(route('admin.quizzes.analysis', $quiz))->assertRedirect(route('login'));
    }
}
