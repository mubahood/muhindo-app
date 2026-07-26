<?php

namespace App\Services\Learning;

use App\Models\AttemptAnswer;
use App\Models\Quiz;

/**
 * §6.3.4 — quiz item analysis: per-question correct-rate across every attempt that has actually
 * answered it. A question everyone fails is a signal about the question (or the lesson before
 * it), not about the students — this is the instructor-facing view that surfaces it.
 */
class QuizAnalysisService
{
    /** @return array<int, array{question_id: int, prompt: string, type: string, total_answered: int, correct_count: int, correct_rate: ?float}> */
    public function itemAnalysisFor(Quiz $quiz): array
    {
        $rows = [];

        foreach ($quiz->questions()->get() as $question) {
            $answers = AttemptAnswer::where('question_id', $question->id)->whereNotNull('is_correct')->get();
            $total = $answers->count();
            $correct = $answers->where('is_correct', true)->count();

            $rows[] = [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type->label(),
                'total_answered' => $total,
                'correct_count' => $correct,
                'correct_rate' => $total > 0 ? round(($correct / $total) * 100, 1) : null,
            ];
        }

        return $rows;
    }
}
