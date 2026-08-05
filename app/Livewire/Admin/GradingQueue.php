<?php

namespace App\Livewire\Admin;

use App\Models\AssignmentSubmission;
use App\Models\AttemptAnswer;
use App\Services\Learning\AssignmentService;
use App\Services\Learning\QuizService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * "your daily inbox": every ungraded quiz answer (essay/unmatched-short_text) and
 * every submitted-but-not-returned assignment, across every course, oldest first.
 */
class GradingQueue extends Component
{
    public ?string $gradingType = null;

    public ?int $gradingId = null;

    public string $points = '';

    public string $feedback = '';

    public function openGrading(string $type, int $id): void
    {
        $this->gradingType = $type;
        $this->gradingId = $id;
        $this->points = '';
        $this->feedback = '';
        $this->resetErrorBag();
    }

    public function cancelGrading(): void
    {
        $this->reset(['gradingType', 'gradingId', 'points', 'feedback']);
    }

    public function submitGrade(QuizService $quizzes, AssignmentService $assignments): void
    {
        $data = $this->validate([
            'points' => 'required|numeric|min:0',
            'feedback' => 'nullable|string|max:5000',
        ]);

        if ($this->gradingType === 'quiz_answer') {
            $answer = AttemptAnswer::with('attempt', 'question')->findOrFail($this->gradingId);
            $quizzes->gradeManual($answer->attempt, $answer->question, (float) $data['points'], $data['feedback'] ?: null);
        } else {
            $submission = AssignmentSubmission::findOrFail($this->gradingId);
            $assignments->return($submission, (float) $data['points'], $data['feedback'] ?: null, auth()->user());
        }

        session()->flash('success', 'Grade saved.');
        $this->cancelGrading();
    }

    public function render(): View
    {
        return view('livewire.admin.grading-queue', ['queue' => $this->pendingQueue()])
            ->layout('layouts.admin')
            ->title('Grading Queue');
    }

    private function pendingQueue(): Collection
    {
        $rows = [];

        foreach (AttemptAnswer::where('auto_graded', false)
            ->whereNull('points_awarded')
            ->whereHas('attempt', fn ($q) => $q->where('status', 'submitted'))
            ->with(['attempt.enrollment.user', 'attempt.quiz.course', 'question'])
            ->get() as $answer) {
            $rows[] = [
                'type' => 'quiz_answer',
                'id' => $answer->id,
                'student' => $answer->attempt->enrollment->user->name,
                'course' => $answer->attempt->quiz->course->title,
                'title' => $answer->attempt->quiz->title.' - '.Str::limit(strip_tags($answer->question->prompt), 60),
                'submitted_at' => $answer->attempt->submitted_at,
                'max_points' => (float) $answer->question->points,
            ];
        }

        foreach (AssignmentSubmission::where('status', 'submitted')
            ->with(['enrollment.user', 'assignment.course'])
            ->get() as $submission) {
            $rows[] = [
                'type' => 'submission',
                'id' => $submission->id,
                'student' => $submission->enrollment->user->name,
                'course' => $submission->assignment->course->title,
                'title' => $submission->assignment->title.($submission->is_late ? ' (late)' : ''),
                'submitted_at' => $submission->submitted_at,
                'max_points' => (float) $submission->assignment->points,
            ];
        }

        usort($rows, fn (array $a, array $b) => ($a['submitted_at'] ?? now())->timestamp <=> ($b['submitted_at'] ?? now())->timestamp);

        return collect($rows);
    }
}
