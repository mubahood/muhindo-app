<?php

namespace App\Livewire\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Notifications\EnrollmentCancelledNotification;
use App\Notifications\StudentNudgeNotification;
use App\Services\BillingService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * §6.3.2 — the per-student drill-down: activity timeline, lesson-by-lesson
 * progress, private instructor notes, a one-click nudge, and (§7.1) cancel +
 * refund. "reset quiz attempts"/"extend access" buttons remain deferred — the
 * latter needs an enrollment expiry concept that doesn't exist until P5.
 */
class EnrollmentDrilldown extends Component
{
    use WithPagination;

    public Enrollment $enrollment;

    public string $newNote = '';

    public bool $nudgeSent = false;

    public bool $cancelled = false;

    public function mount(Enrollment $enrollment): void
    {
        $this->enrollment = $enrollment->load(['user', 'course', 'lastLesson', 'notes.user']);
    }

    /** §7.1 — revokes access immediately; credits the funding invoice if one was actually paid. */
    public function cancelAndRefund(BillingService $billing): void
    {
        $refunded = false;

        if ($this->enrollment->invoice_id) {
            $invoice = Invoice::find($this->enrollment->invoice_id);
            if ($invoice && in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::PartiallyPaid], true)) {
                $billing->refund($invoice, auth()->id());
                $refunded = true;
            }
        }

        $this->enrollment->update(['status' => 'cancelled']);
        $this->enrollment->user->notify(new EnrollmentCancelledNotification($this->enrollment, $refunded));

        $this->cancelled = true;
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
