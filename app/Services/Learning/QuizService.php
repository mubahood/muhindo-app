<?php

namespace App\Services\Learning;

use App\Enums\QuestionType;
use App\Enums\QuizAttemptStatus;
use App\Events\Learning\QuizAttemptSubmitted;
use App\Models\Enrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** §5.2 — the attempt lifecycle: start → answer (autosave) → submit (server-graded). Server is the only source of truth. */
class QuizService
{
    /** Starts a new attempt, or resumes the caller's existing in-progress one — never a second parallel attempt. */
    public function start(Quiz $quiz, Enrollment $enrollment): QuizAttempt
    {
        Gate::authorize('access', $enrollment);

        if ($quiz->course_id !== $enrollment->course_id) {
            throw new HttpException(404);
        }

        if (! $quiz->is_published) {
            throw new HttpException(404);
        }

        if (! $quiz->isAvailableNow()) {
            throw new HttpException(403, 'This quiz is not currently available.');
        }

        $existing = $quiz->attempts()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', QuizAttemptStatus::InProgress)
            ->latest('attempt_no')
            ->first();

        if ($existing) {
            return $existing;
        }

        $attemptCount = $quiz->attempts()->where('enrollment_id', $enrollment->id)->count();

        if ($quiz->max_attempts && $attemptCount >= $quiz->max_attempts) {
            throw new HttpException(403, 'You have used all of your attempts for this quiz.');
        }

        return $quiz->attempts()->create([
            'uuid' => (string) Str::uuid(),
            'enrollment_id' => $enrollment->id,
            'attempt_no' => $attemptCount + 1,
            'status' => QuizAttemptStatus::InProgress,
            'started_at' => now(),
            'question_order' => $this->buildQuestionOrder($quiz),
        ]);
    }

    /** Autosaves a single question's answer. Safe to call repeatedly as the student changes their mind. */
    public function answer(QuizAttempt $attempt, Question $question, ?array $payload): \App\Models\AttemptAnswer
    {
        Gate::authorize('access', $attempt->enrollment);

        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            throw new HttpException(409, 'This attempt is no longer in progress.');
        }

        $questionIds = $attempt->question_order['questions'] ?? [];
        if (! in_array($question->id, $questionIds, true)) {
            throw new HttpException(404);
        }

        return $attempt->answers()->updateOrCreate(
            ['question_id' => $question->id],
            ['answer' => $payload, 'answered_at' => now()],
        );
    }

    /** Grades whatever was answered, closes the attempt, and dispatches QuizAttemptSubmitted when nothing is left for a human. */
    public function submit(QuizAttempt $attempt, ?array $integrity = null): QuizAttempt
    {
        Gate::authorize('access', $attempt->enrollment);

        if ($attempt->status !== QuizAttemptStatus::InProgress) {
            throw new HttpException(409, 'This attempt has already been submitted.');
        }

        $quiz = $attempt->quiz;

        if ($quiz->time_limit_minutes && $attempt->started_at) {
            $deadline = $attempt->started_at->addMinutes($quiz->time_limit_minutes)->addSeconds(30);
            if (now()->gt($deadline)) {
                throw new HttpException(422, 'The time limit for this attempt has passed.');
            }
        }

        $questionIds = $attempt->question_order['questions'] ?? [];
        $questions = Question::whereIn('id', $questionIds)->with('options')->get()->keyBy('id');

        $totalPoints = 0.0;
        $earnedPoints = 0.0;
        $needsManualReview = false;

        foreach ($questionIds as $questionId) {
            $question = $questions->get($questionId);

            if (! $question) {
                continue;
            }

            $totalPoints += (float) $question->points;

            $answerRow = $attempt->answers()->firstOrCreate(['question_id' => $questionId]);
            $result = $this->gradeAnswer($question, $answerRow->answer);

            $answerRow->update([
                'is_correct' => $result['is_correct'],
                'points_awarded' => $result['points_awarded'],
                'auto_graded' => $result['auto_graded'],
            ]);

            if ($result['auto_graded']) {
                $earnedPoints += $result['points_awarded'] ?? 0.0;
            } else {
                $needsManualReview = true;
            }
        }

        $timeSpent = $attempt->started_at ? $attempt->started_at->diffInSeconds(now()) : null;

        if ($needsManualReview) {
            $attempt->update([
                'status' => QuizAttemptStatus::Submitted,
                'submitted_at' => now(),
                'max_points' => $totalPoints,
                'time_spent_seconds' => $timeSpent,
                'integrity' => $integrity,
            ]);

            return $attempt->fresh();
        }

        $percent = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0.0;

        $attempt->update([
            'status' => QuizAttemptStatus::Graded,
            'submitted_at' => now(),
            'graded_at' => now(),
            'score_points' => $earnedPoints,
            'max_points' => $totalPoints,
            'score_percent' => $percent,
            'passed' => $percent >= $quiz->pass_percent,
            'time_spent_seconds' => $timeSpent,
            'integrity' => $integrity,
        ]);

        $attempt = $attempt->fresh();
        QuizAttemptSubmitted::dispatch($attempt);

        return $attempt;
    }

    /** Freezes question order (respecting the pool draw) and, per question, option order — so a resumed attempt is stable. */
    private function buildQuestionOrder(Quiz $quiz): array
    {
        $questions = $quiz->questions()->with('options')->get();
        $ids = $questions->pluck('id')->all();

        if ($quiz->questions_per_attempt && $quiz->questions_per_attempt < count($ids)) {
            $pool = $ids;
            shuffle($pool);
            $drawn = array_slice($pool, 0, $quiz->questions_per_attempt);
            $ids = array_values(array_intersect($ids, $drawn));
        }

        if ($quiz->shuffle_questions) {
            shuffle($ids);
        }

        $optionOrder = [];
        foreach ($questions->whereIn('id', $ids) as $question) {
            $optionIds = $question->options->pluck('id')->all();

            if ($question->type === QuestionType::Ordering || $quiz->shuffle_options) {
                shuffle($optionIds);
            }

            $optionOrder[$question->id] = $optionIds;
        }

        return ['questions' => array_values($ids), 'options' => $optionOrder];
    }

    /** @return array{is_correct: ?bool, points_awarded: ?float, auto_graded: bool} */
    private function gradeAnswer(Question $question, ?array $answer): array
    {
        if ($answer === null || $answer === []) {
            return ['is_correct' => false, 'points_awarded' => 0.0, 'auto_graded' => true];
        }

        return match ($question->type) {
            QuestionType::McqSingle, QuestionType::TrueFalse => $this->gradeSingleChoice($question, $answer),
            QuestionType::McqMulti => $this->gradeMultiChoice($question, $answer),
            QuestionType::FillBlank => $this->gradeTextMatch($question, $answer),
            QuestionType::Numeric => $this->gradeNumeric($question, $answer),
            QuestionType::Matching => $this->gradeMatching($question, $answer),
            QuestionType::Ordering => $this->gradeOrdering($question, $answer),
            QuestionType::ShortText => $this->gradeShortText($question, $answer),
            QuestionType::Essay => ['is_correct' => null, 'points_awarded' => null, 'auto_graded' => false],
        };
    }

    private function gradeSingleChoice(Question $question, array $answer): array
    {
        $selectedId = isset($answer['selected']) ? (int) $answer['selected'] : null;
        $correctOption = $question->options->firstWhere('is_correct', true);
        $isCorrect = $selectedId !== null && $correctOption && $selectedId === $correctOption->id;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? (float) $question->points : 0.0,
            'auto_graded' => true,
        ];
    }

    private function gradeMultiChoice(Question $question, array $answer): array
    {
        $selected = array_map('intval', $answer['selected'] ?? []);
        $correctIds = $question->options->where('is_correct', true)->pluck('id')->all();
        $totalCorrect = count($correctIds);

        if ($totalCorrect === 0) {
            return ['is_correct' => false, 'points_awarded' => 0.0, 'auto_graded' => true];
        }

        $correctPicked = count(array_intersect($selected, $correctIds));
        $wrongPicked = count(array_diff($selected, $correctIds));
        $fraction = max(0, ($correctPicked - $wrongPicked) / $totalCorrect);

        return [
            'is_correct' => $fraction >= 1.0 && $wrongPicked === 0,
            'points_awarded' => round($fraction * (float) $question->points, 2),
            'auto_graded' => true,
        ];
    }

    private function gradeNumeric(Question $question, array $answer): array
    {
        if (! isset($answer['value']) || ! is_numeric($answer['value'])) {
            return ['is_correct' => false, 'points_awarded' => 0.0, 'auto_graded' => true];
        }

        $expected = (float) ($question->meta['expected'] ?? 0);
        $tolerance = (float) ($question->meta['tolerance'] ?? 0);
        $isCorrect = abs((float) $answer['value'] - $expected) <= $tolerance;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? (float) $question->points : 0.0,
            'auto_graded' => true,
        ];
    }

    private function gradeMatching(Question $question, array $answer): array
    {
        $pairs = $answer['pairs'] ?? [];
        $options = $question->options;
        $total = $options->count();

        if ($total === 0) {
            return ['is_correct' => false, 'points_awarded' => 0.0, 'auto_graded' => true];
        }

        $correctCount = 0;
        foreach ($options as $option) {
            $studentMatch = $pairs[$option->id] ?? null;
            if ($studentMatch !== null && trim((string) $studentMatch) === trim((string) $option->match_key)) {
                $correctCount++;
            }
        }

        $fraction = $correctCount / $total;

        return [
            'is_correct' => $fraction === 1.0,
            'points_awarded' => round($fraction * (float) $question->points, 2),
            'auto_graded' => true,
        ];
    }

    private function gradeOrdering(Question $question, array $answer): array
    {
        $studentOrder = array_map('intval', $answer['order'] ?? []);
        $correctOrder = $question->options->sortBy('sort_order')->pluck('id')->values()->all();
        $isCorrect = $studentOrder === $correctOrder;

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? (float) $question->points : 0.0,
            'auto_graded' => true,
        ];
    }

    /** fill_blank always auto-grades (wrong if unmatched); short_text uses the same match but flags a miss for review. */
    private function gradeTextMatch(Question $question, array $answer): array
    {
        $isCorrect = $this->textMatchesAcceptedAnswer($question, (string) ($answer['text'] ?? ''));

        return [
            'is_correct' => $isCorrect,
            'points_awarded' => $isCorrect ? (float) $question->points : 0.0,
            'auto_graded' => true,
        ];
    }

    private function gradeShortText(Question $question, array $answer): array
    {
        if ($this->textMatchesAcceptedAnswer($question, (string) ($answer['text'] ?? ''))) {
            return ['is_correct' => true, 'points_awarded' => (float) $question->points, 'auto_graded' => true];
        }

        return ['is_correct' => null, 'points_awarded' => null, 'auto_graded' => false];
    }

    private function textMatchesAcceptedAnswer(Question $question, string $text): bool
    {
        $accepted = $question->meta['accepted_answers'] ?? [];
        $caseSensitive = (bool) ($question->meta['case_sensitive'] ?? false);

        $normalize = function (string $value) use ($caseSensitive): string {
            $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

            return $caseSensitive ? $value : mb_strtolower($value);
        };

        $normalizedText = $normalize($text);
        if ($normalizedText === '') {
            return false;
        }

        foreach ($accepted as $candidate) {
            if ($normalize((string) $candidate) === $normalizedText) {
                return true;
            }
        }

        return false;
    }
}
