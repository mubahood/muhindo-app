<?php

namespace App\Services\Learning;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Gate;

/**
 * The only writer of lesson_progress, enrollment completion, certificate
 * issuance, and the §6.1 denormalized fast-path columns (`progress_percent`,
 * `last_lesson_id`, `last_accessed_at`). The web player and the API both call
 * this instead of writing completion state themselves, so a rule added here
 * (sequencing, watch-time gates, …) can never be true on one surface and false
 * on the other — kills L14. Authorizing here (not just in the calling
 * controller) means the check can't be forgotten by a future caller, closing
 * the gap the API previously had (no enrollment-status check on
 * `completeLesson`).
 */
class ProgressService
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::authorize('access', $enrollment);

        $progress = $enrollment->progressRecords()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()],
        );

        $percent = $enrollment->progressPercent();
        $enrollment->update([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
            'progress_percent' => $percent,
        ]);

        if ($percent >= 100 && $enrollment->status !== 'completed') {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            $this->certificates->issue($enrollment);
        }

        return $progress;
    }

    /** Records that the student viewed (not necessarily completed) a lesson — feeds resume UX (§6.5). */
    public function recordView(Enrollment $enrollment, Lesson $lesson): void
    {
        Gate::authorize('access', $enrollment);

        $enrollment->update([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
            'progress_percent' => $enrollment->progressPercent(),
        ]);
    }
}
