<?php

namespace App\Livewire\Admin;

use App\Models\Enrollment;
use App\Notifications\StudentNudgeNotification;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * §6.3.2 — the per-student drill-down: activity timeline, lesson-by-lesson
 * progress, private instructor notes, and a one-click nudge. Grade/attempt
 * history and "reset quiz attempts"/"extend access" buttons are deferred —
 * the former needs the P3 quiz/assignment models, the latter an enrollment
 * expiry concept that doesn't exist until P5.
 */
class EnrollmentDrilldown extends Component
{
    use WithPagination;

    public Enrollment $enrollment;

    public string $newNote = '';

    public bool $nudgeSent = false;

    public function mount(Enrollment $enrollment): void
    {
        $this->enrollment = $enrollment->load(['user', 'course', 'lastLesson', 'notes.user']);
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:2000']);

        $this->enrollment->notes()->create([
            'user_id' => auth()->id(),
            'note' => $this->newNote,
        ]);

        $this->newNote = '';
        $this->enrollment->load('notes.user');
    }

    public function sendNudge(): void
    {
        $this->enrollment->user->notify(new StudentNudgeNotification($this->enrollment));
        $this->nudgeSent = true;
    }

    public function render(): View
    {
        $lessons = $this->enrollment->course->modules->flatMap->lessons;
        $completedLessonIds = $this->enrollment->progressRecords()->whereNotNull('completed_at')->pluck('lesson_id');

        $timeline = $this->enrollment->learningEvents()
            ->with('lesson')
            ->latest('created_at')
            ->paginate(20);

        return view('livewire.admin.enrollment-drilldown', [
            'lessons' => $lessons,
            'completedLessonIds' => $completedLessonIds,
            'timeline' => $timeline,
        ])
            ->layout('layouts.admin')
            ->title($this->enrollment->user->name.' — '.$this->enrollment->course->title);
    }
}
