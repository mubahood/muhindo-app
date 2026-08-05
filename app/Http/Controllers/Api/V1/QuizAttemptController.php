<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizFeedbackMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionRunResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Learning\MarkdownRenderer;
use App\Services\Learning\QuizService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The API equivalent of Student\QuizAttemptController's start/run/answer/submit/review
 * flow. Unlike the web version, this expects a native JSON payload from every caller, no
 * "no-JS form" bulk-answers fallback or ordering-question position-input normalization, since
 * a mobile client always sends a well-shaped `{"order": [id, id, id]}` array directly.
 */
class QuizAttemptController extends Controller
{
    public function __construct(
        private readonly QuizService $quizzes,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function start(Request $request, Course $course, Quiz $quiz): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardQuiz($quiz, $course);

        $attempt = $this->quizzes->start($quiz, $enrollment);

        return ApiResponse::success($attempt, null, 201);
    }

    public function show(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            return ApiResponse::error(ApiErrorCode::Forbidden, 'This attempt is no longer in progress, see the review endpoint.', 409);
        }

        $questionIds = $attempt->question_order['questions'] ?? [];
        $optionOrder = $attempt->question_order['options'] ?? [];

        $questionsById = Question::whereIn('id', $questionIds)->with('options')->get()->keyBy('id');
        $orderedQuestions = collect($questionIds)->map(fn ($id) => $questionsById->get($id))->filter()->values();

        $orderedQuestions->each(function (Question $question) use ($optionOrder) {
            $ids = $optionOrder[$question->id] ?? $question->options->pluck('id')->all();
            $question->setRelation('options', $question->options->sortBy(fn ($o) => array_search($o->id, $ids))->values());
        });

        $renderedPrompts = $orderedQuestions->mapWithKeys(fn (Question $q) => [$q->id => $this->markdown->toHtml($q->prompt)]);
        $existingAnswers = $attempt->answers()->get()->mapWithKeys(fn ($a) => [$a->question_id => $a->answer]);
        $deadline = ($quiz->time_limit_minutes && $attempt->started_at)
            ? $attempt->started_at->copy()->addMinutes($quiz->time_limit_minutes)
            : null;

        return ApiResponse::success([
            'attempt' => $attempt,
            'questions' => QuestionRunResource::collection($orderedQuestions),
            'rendered_prompts' => $renderedPrompts,
            'existing_answers' => $existingAnswers,
            'deadline' => $deadline?->toIso8601String(),
        ]);
    }

    public function answer(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt, Question $question): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        $payload = $request->input('answer');
        $saved = $this->quizzes->answer($attempt, $question, is_array($payload) ? $payload : null);

        $data = ['saved' => true];
        if ($quiz->feedback_mode === QuizFeedbackMode::Immediate) {
            $data['preview'] = $this->quizzes->previewGrade($question->fresh('options'), $saved->answer);
        }

        return ApiResponse::success($data);
    }

    public function submit(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        $integrity = $request->input('integrity');
        $result = $this->quizzes->submit($attempt, is_array($integrity) ? $integrity : null);

        return ApiResponse::success($result);
    }

    public function review(Request $request, Course $course, Quiz $quiz, QuizAttempt $attempt): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAttempt($attempt, $quiz, $course, $enrollment);

        return ApiResponse::success([
            'attempt' => $attempt,
            'feedback' => $this->quizzes->feedbackFor($attempt),
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
}
