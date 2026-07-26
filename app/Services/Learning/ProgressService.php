<?php

namespace App\Services\Learning;

use App\Enums\CompletionRule;
use App\Enums\LearningEventType;
use App\Events\Learning\CourseCompleted;
use App\Events\Learning\LessonCompleted;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Gate;

/**
 * The only writer of lesson_progress, enrollment completion, and the §6.1
 * denormalized fast-path columns (`progress_percent`, `last_lesson_id`,
 * `last_accessed_at`). The web player and the API both call this instead of
 * writing completion state themselves, so a rule added here (sequencing,
 * watch-time gates, …) can never be true on one surface and false on the
 * other — kills L14. Authorizing here (not just in the calling controller)
 * means the check can't be forgotten by a future caller, closing the gap the
 * API previously had (no enrollment-status check on `completeLesson`). Also
 * authorizes `LessonPolicy::view` (§4.3 sequential progression — a locked
 * lesson can't be viewed or completed through either surface) and emits the
 * corresponding `learning_events` row (§6.2) for every action it writes.
 *
 * Certificate issuance, completion notifications, and enrollment-welcome
 * notifications are §4.5 side-effects and deliberately live in queued
 * listeners on `LessonCompleted`/`CourseCompleted`/`EnrollmentCreated`
 * (registered in `AppServiceProvider`), not here — this service only decides
 * *that* something completed, never what happens as a result.
 */
class ProgressService
{
    public function __construct(private readonly LearningEventRecorder $events) {}

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        $progress = $enrollment->progressRecords()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()],
        );
        $this->events->record($enrollment, LearningEventType::LessonCompleted, $lesson);
        LessonCompleted::dispatch($enrollment, $lesson);

        $percent = $enrollment->progressPercent();
        $enrollment->update([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
            'progress_percent' => $percent,
        ]);

        if ($percent >= 100 && $enrollment->status !== 'completed') {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            CourseCompleted::dispatch($enrollment);
        }

        return $progress;
    }

    /** Records that the student viewed (not necessarily completed) a lesson — feeds resume UX (§6.5). */
    public function recordView(Enrollment $enrollment, Lesson $lesson): void
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        $enrollment->update([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
            'progress_percent' => $enrollment->progressPercent(),
        ]);
        $this->events->record($enrollment, LearningEventType::LessonViewed, $lesson);
    }

    /**
     * §6.2/§4.3 — every ~15s of actual playing time, the player reports how
     * much it played and where it is. This is the only place `watch_seconds`/
     * `last_position_seconds`/`total_watch_seconds` are written, and the only
     * place `min_watch` completion is decided — server-side, from accumulated
     * telemetry, never from a client "I finished" claim. `$secondsDelta` and
     * `$positionSeconds` are clamped defensively: a heartbeat fires roughly
     * every 15s, so nothing legitimate ever reports more than that per call.
     */
    public function recordHeartbeat(Enrollment $enrollment, Lesson $lesson, int $secondsDelta, int $positionSeconds): LessonProgress
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        $secondsDelta = max(0, min($secondsDelta, 30));
        $durationSeconds = $lesson->durationSeconds();
        $positionSeconds = $durationSeconds ? max(0, min($positionSeconds, $durationSeconds)) : max(0, $positionSeconds);

        $progress = $enrollment->progressRecords()->firstOrNew(['lesson_id' => $lesson->id]);
        $progress->started_at = $progress->started_at ?? now();
        $progress->watch_seconds = ($progress->watch_seconds ?? 0) + $secondsDelta;
        $progress->last_position_seconds = $positionSeconds;
        $progress->save();

        if ($secondsDelta > 0) {
            $enrollment->increment('total_watch_seconds', $secondsDelta);
        }

        $this->events->record($enrollment, LearningEventType::VideoHeartbeat, $lesson, null, [
            'position_seconds' => $positionSeconds,
            'seconds_delta' => $secondsDelta,
        ]);

        if (! $progress->completed_at && $this->minWatchThresholdCrossed($lesson, $progress)) {
            $this->completeLesson($enrollment, $lesson);
            $progress->refresh();
        }

        return $progress;
    }

    private function minWatchThresholdCrossed(Lesson $lesson, LessonProgress $progress): bool
    {
        if ($lesson->completion_rule !== CompletionRule::MinWatch) {
            return false;
        }

        $duration = $lesson->durationSeconds();
        if (! $duration || $duration <= 0) {
            return false;
        }

        return ($progress->watch_seconds / $duration) * 100 >= $lesson->completion_threshold;
    }
}
