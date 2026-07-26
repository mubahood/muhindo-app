<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuizFeedbackMode;
use App\Enums\QuizGradingMethod;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Services\Learning\QuizAnalysisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function analysis(Quiz $quiz, QuizAnalysisService $analysis): View
    {
        return view('admin.quizzes.analysis', [
            'quiz' => $quiz,
            'items' => $analysis->itemAnalysisFor($quiz),
        ]);
    }

    public function create(Course $course): View
    {
        return view('admin.quizzes.form', ['course' => $course, 'quiz' => new Quiz]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $course->quizzes()->create($this->validated($request));

        return redirect()->route('admin.courses.show', $course)->with('success', 'Quiz created.');
    }

    public function edit(Quiz $quiz): View
    {
        return view('admin.quizzes.form', ['course' => $quiz->course, 'quiz' => $quiz->load('questions.options')]);
    }

    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $quiz->update($this->validated($request));

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $course = $quiz->course;
        $quiz->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Quiz deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'max_attempts' => 'nullable|integer|min:1',
            'pass_percent' => 'required|integer|min:0|max:100',
            'grading_method' => ['required', Rule::in(array_column(QuizGradingMethod::cases(), 'value'))],
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'questions_per_attempt' => 'nullable|integer|min:1',
            'one_question_per_page' => 'nullable|boolean',
            'feedback_mode' => ['required', Rule::in(array_column(QuizFeedbackMode::cases(), 'value'))],
            'counts_toward_certificate' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after_or_equal:available_from',
        ]);

        foreach (['shuffle_questions', 'shuffle_options', 'one_question_per_page', 'counts_toward_certificate', 'is_published'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
