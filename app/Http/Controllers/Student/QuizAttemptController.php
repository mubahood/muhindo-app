<?php

namespace App\Http\Controllers\Student;

use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizFeedbackMode;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Learning\MarkdownRenderer;
use App\Services\Learning\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §5.2/§7 — the student-facing quiz runner: list → intro/start → take (AJAX autosave) → review. */
class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function index(Request $request, Course $course): View
    {
        $enrollment = $this->enrollmentFor($request, $course);

        $quizzes = $course->quizzes()->where('is_published', true)->with('lesson')->get()
            ->map(function (Quiz $quiz) use ($enrollment) {
                $latest = $quiz->attempts()->where('enrollment_id', $enrollment->id)->latest('attempt_no')->first();

                return ['quiz' => $quiz, 'latest' => $latest];
            });

        return view('learn.quizzes.index', ['course' => $course, 'quizzes' => $quizzes]);
    }

    public function show(Request $request, Course $course, Quiz $quiz): View
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardQuiz($quiz, $course);
        abort_unless($quiz->is_published, 404);

        $attemptsUsed = $quiz->attempts()->where('enrollment_id', $enrollment->id)->count();
        $inProgress = $quiz->attempts()->where('enrollment_id', $enrollment->id)
            ->where('status', QuizAttemptStatus::InProgress)->first();
        $bestAttempt = $quiz->attempts()->where('enrollment_id', $enrollment->id)
            ->where('status', QuizAttemptStatus::Graded)->orderByDesc('score_percent')->first();

        return view('learn.quizzes.show', [
            'course' => $course, 'quiz' => $quiz,
            'attemptsUsed' => $attemptsUsed, 'inProgress' => $inProgress, 'bestAttempt' => $bestAttempt,
        ]);
    }

    public function start(Request $request, Course $course, Quiz $quiz): RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardQuiz($quiz, $course);

        $attempt = $this->quizzes->start($quiz, $enrollment);

        return redirect()->route('learn.quiz.attempt', [$course, $quiz, $attempt]);
    }

    public function run(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): View|RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            return redirect()->route('learn.quiz.review', [$course, $quiz, $attempt]);
        }

        $questionIds = $attempt->question_order['questions'] ?? [];
        $optionOrder = $attempt->question_order['options'] ?? [];

        $questionsById = Question::whereIn('id', $questionIds)->with('options')->get()->keyBy('id');
        $orderedQuestions = collect($questionIds)->map(fn ($id) => $questionsById->get($id))->filter()->values();

        // Re-order each question's options to the frozen per-attempt shuffle.
        $orderedQuestions->each(function (Question $question) use ($optionOrder) {
            $ids = $optionOrder[$question->id] ?? $question->options->pluck('id')->all();
            $question->setRelation('options', $question->options->sortBy(fn ($o) => array_search($o->id, $ids))->values());
        });

        $existingAnswers = $attempt->answers()->get()->keyBy('question_id');
        $renderedPrompts = $orderedQuestions->mapWithKeys(fn (Question $q) => [$q->id => $this->markdown->toHtml($q->prompt)]);
        $deadline = ($quiz->time_limit_minutes && $attempt->started_at)
            ? $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes)
            : null;

        return view('learn.quizzes.run', [
            'course' => $course, 'quiz' => $quiz, 'attempt' => $attempt,
            'orderedQuestions' => $orderedQuestions, 'existingAnswers' => $existingAnswers,
            'renderedPrompts' => $renderedPrompts, 'deadline' => $deadline,
        ]);
    }

    public function answer(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt, Question $question): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        $payload = $this->normalizePayload($question, $request->input('answer'));
        $saved = $this->quizzes->answer($attempt, $question, $payload);

        $response = ['success' => true];

        if ($quiz->feedback_mode === QuizFeedbackMode::Immediate) {
            $response['preview'] = $this->quizzes->previewGrade($question->fresh('options'), $saved->answer);
        }

        return response()->json($response);
    }

    /**
     * Also accepts a full `answers` map so a plain (no-JS) form submit works: every question's
     * final value is saved before the attempt is graded, not just whatever autosave already sent.
     */
    public function submit(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): RedirectResponse|JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        if ($attempt->status === QuizAttemptStatus::InProgress) {
            $questionIds = $attempt->question_order['questions'] ?? [];
            $questions = Question::whereIn('id', $questionIds)->get()->keyBy('id');

            foreach ($request->input('answers', []) as $questionId => $payload) {
                $question = $questions->get((int) $questionId);
                if ($question && is_array($payload)) {
                    $this->quizzes->answer($attempt, $question, $this->normalizePayload($question, $payload));
                }
            }
        }

        $integrity = $request->input('integrity');
        $result = $this->quizzes->submit($attempt, is_array($integrity) ? $integrity : null);
        $reviewUrl = route('learn.quiz.review', [$course, $quiz, $result]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'redirect' => $reviewUrl]);
        }

        return redirect($reviewUrl);
    }

    public function review(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): View
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        $feedback = $this->quizzes->feedbackFor($attempt);
        $questions = $feedback
            ? Question::whereIn('id', array_column($feedback, 'question_id'))->with('options')->get()->keyBy('id')
            : collect();
        $renderedPrompts = $questions->mapWithKeys(fn (Question $q) => [$q->id => $this->markdown->toHtml($q->prompt)]);

        return view('learn.quizzes.review', [
            'course' => $course, 'quiz' => $quiz, 'attempt' => $attempt,
            'feedback' => $feedback ? collect($feedback)->keyBy('question_id') : null,
            'questions' => $questions, 'renderedPrompts' => $renderedPrompts,
        ]);
    }

    private function enrollmentFor(Request $request, Course $course): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return $enrollment;
    }

    private function guardQuiz(Quiz $quiz, Course $course): void
    {
        abort_unless($quiz->course_id === $course->id, 404);
    }

    private function guardAttempt(QuizAttempt $attempt, Quiz $quiz, Course $course, Enrollment $enrollment): void
    {
        $this->guardQuiz($quiz, $course);
        abort_unless($attempt->quiz_id === $quiz->id, 404);
        abort_unless($attempt->enrollment_id === $enrollment->id, 404);
    }

    /**
     * Ordering questions render as numbered "position" inputs (works with or without JS); an
     * associative {optionId: position} payload is collapsed here into the sequential id list
     * QuizService::gradeOrdering() expects. Every other type passes through untouched.
     */
    private function normalizePayload(Question $question, ?array $payload): ?array
    {
        if ($payload === null || $question->type !== QuestionType::Ordering || ! is_array($payload['order'] ?? null)) {
            return $payload;
        }

        if (array_keys($payload['order']) === range(0, count($payload['order']) - 1)) {
            return $payload;
        }

        $payload['order'] = collect($payload['order'])
            ->sortBy(fn ($position) => (int) $position)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $payload;
    }
}
