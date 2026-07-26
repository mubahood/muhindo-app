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
 * progress, private instructor notes, a one-click nudge, (§7.1) cancel +
 * refund, and (§6.4/P5.2) extend/remove the access-window expiry. "Reset
 * quiz attempts" remains deferred — nothing in the plan scopes it to a
 * specific phase, and it isn't needed for any P5 item.
 */
class EnrollmentDrilldown extends Component
{
    use WithPagination;

    public Enrollment $enrollment;

    public string $newNote = '';

    public int $extendByDays = 30;

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

    /** §6.4/P5.2 — extends from the later of "now" or the current expiry, so a lapsed window doesn't shortchange the extension. */
    public function extendAccess(): void
    {
        $this->validate(['extendByDays' => 'required|integer|min:1|max:3650']);

        $base = $this->enrollment->expires_at && $this->enrollment->expires_at->isFuture()
            ? $this->enrollment->expires_at
            : now();

        $this->enrollment->update(['expires_at' => $base->copy()->addDays($this->extendByDays)]);
    }

    public function removeExpiry(): void
    {
        $this->enrollment->update(['expires_at' => null]);
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
