<?php

namespace App\Services\Learning;

use App\Enums\CompletionRule;
use App\Enums\LearningEventType;
use App\Events\Learning\CourseCompleted;
use App\Events\Learning\LessonCompleted;
use App\Exceptions\LessonCompletionBlockedException;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Support\Facades\Gate;

/**
 * The only writer of lesson_progress, enrollment completion, and the
 * denormalized fast-path columns (`progress_percent`, `last_lesson_id`,
 * `last_accessed_at`). The web player and the API both call this instead of
 * writing completion state themselves, so a rule added here (sequencing,
 * watch-time gates, ...) can never be true on one surface and false on the
 * Other, covers this. Authorizing here (not just in the calling controller)
 * means the check can't be forgotten by a future caller, closing the gap the
 * API previously had (no enrollment-status check on `completeLesson`). Also
 * authorizes `LessonPolicy::view` (sequential progression. A locked
 * lesson can't be viewed or completed through either surface) and emits the
 * corresponding `learning_events` row for every action it writes.
 *
 * Certificate issuance, completion notifications, and enrollment-welcome
 * notifications are side-effects and deliberately live in queued
 * listeners on `LessonCompleted`/`CourseCompleted`/`EnrollmentCreated`
 * (registered in `AppServiceProvider`), not here. This service only decides
 * *that* something completed, never what happens as a result.
 */
class ProgressService
{
    public function __construct(private readonly LearningEventRecorder $events) {}

    public function completeLesson(Enrollment $enrollment, Lesson $lesson): LessonProgress
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        // Enforced here, not in the controllers, so no surface (web, API, or a
        // future caller) can complete a lesson whose requirements aren't met.
        // An already-completed lesson bypasses the check: re-completion is an
        // idempotent no-op (stale tab, the "Next lesson" button re-posting) and
        // must never start failing because a requirement was added afterwards.
        $alreadyCompleted = $enrollment->progressRecords()
            ->where('lesson_id', $lesson->id)->whereNotNull('completed_at')->exists();
        if (! $alreadyCompleted) {
            $blockers = $this->completionBlockers($enrollment, $lesson);
            if ($blockers !== []) {
                throw new LessonCompletionBlockedException($blockers);
            }
        }

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

    /**
     * Records that the student viewed (not necessarily completed) a lesson, feeds resume UX
     * A `completed` enrollment's `progress_percent` freezes at 100 permanently: if
     * the course's curriculum grows later (a new lesson added), a completed student revisiting
     * any old lesson must never have their snapshot silently recomputed downward against the
     * new, larger lesson count.
     */
    public function recordView(Enrollment $enrollment, Lesson $lesson): void
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        $enrollment->update([
            'last_lesson_id' => $lesson->id,
            'last_accessed_at' => now(),
            'progress_percent' => $enrollment->status === 'completed' ? $enrollment->progress_percent : $enrollment->progressPercent(),
        ]);
        $this->events->record($enrollment, LearningEventType::LessonViewed, $lesson);
    }

    /**
     * Every ~15s of actual playing time, the player reports how
     * much it played and where it is. This is the only place `watch_seconds`/
     * `last_position_seconds`/`total_watch_seconds` are written, and the only
     * place `min_watch` completion is decided, server-side, from accumulated
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

        if (! $progress->completed_at
            && $this->minWatchThresholdCrossed($lesson, $progress)
            && $this->completionBlockers($enrollment, $lesson) === []) {
            // Blockers silently defer auto-completion. The next heartbeat after the
            // requirement is met (enough focused time / quiz submitted) completes it.
            $this->completeLesson($enrollment, $lesson);
            $progress->refresh();
        }

        return $progress;
    }

    /**
     * Everything currently standing between this student and completing this lesson:
     * unmet minimum focused time (lessons.min_active_seconds vs. the active_seconds
     * tracker), and required-but-unsubmitted quizzes/assignments attached to the
     * lesson. An empty array means completion is allowed.
     *
     * @return array<int,array<string,mixed>>
     */
    public function completionBlockers(Enrollment $enrollment, Lesson $lesson): array
    {
        /*
         * Debug mode lifts every pacing gate on this course at once.
         *
         * Enforced here rather than at each call site because this method is
         * the single authority on whether a lesson may be completed, the
         * complete endpoint, the auto-complete path and the lesson page all
         * ask it. A bypass added anywhere else would be a second answer to the
         * same question, and the two would drift.
         */
        if ($lesson->course()?->debug_mode) {
            return [];
        }

        $blockers = [];

        if ($lesson->min_active_seconds) {
            $active = (int) ($enrollment->progressRecords()->where('lesson_id', $lesson->id)->value('active_seconds') ?? 0);
            if ($active < $lesson->min_active_seconds) {
                $remaining = $lesson->min_active_seconds - $active;
                $blockers[] = [
                    'type' => 'min_time',
                    'remaining_seconds' => $remaining,
                    'message' => 'Spend at least '.ceil($lesson->min_active_seconds / 60).' minute(s) on this lesson before completing it '
                        .ceil($remaining / 60).' minute(s) to go.',
                ];
            }
        }

        $pendingQuizzes = $lesson->quizzes()
            ->where('is_published', true)->where('is_required', true)
            ->whereDoesntHave('attempts', fn ($q) => $q->where('enrollment_id', $enrollment->id)->whereNotNull('submitted_at'))
            ->get();
        foreach ($pendingQuizzes as $quiz) {
            $blockers[] = [
                'type' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'message' => "Submit the required quiz \"{$quiz->title}\" before completing this lesson.",
            ];
        }

        $pendingAssignments = $lesson->assignments()
            ->where('is_published', true)->where('is_required', true)
            ->whereDoesntHave('submissions', fn ($q) => $q->where('enrollment_id', $enrollment->id)->whereNotNull('submitted_at'))
            ->get();
        foreach ($pendingAssignments as $assignment) {
            $blockers[] = [
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'message' => "Submit the required assignment \"{$assignment->title}\" before completing this lesson.",
            ];
        }

        return $blockers;
    }

    /**
     * Focused-time beat: total engaged seconds on a lesson (reading or watching), sent
     * by the frontend only while the tab is visible AND focused. Same gates and the
     * same 30s-per-beat clamp as the video heartbeat, so a tampered client can't bank
     * more than ~2x real time. Deliberately records no learning_event per beat (unlike
     * the video heartbeat), this fires on every lesson a student ever reads, and the
     * running total on lesson_progress is the queryable fact; per-beat events would
     * only flood the stream.
     */
    public function recordActiveTime(Enrollment $enrollment, Lesson $lesson, int $activeDelta): LessonProgress
    {
        Gate::authorize('access', $enrollment);
        Gate::authorize('view', [$lesson, $enrollment]);

        $activeDelta = max(0, min($activeDelta, 30));

        $progress = $enrollment->progressRecords()->firstOrNew(['lesson_id' => $lesson->id]);
        $progress->started_at = $progress->started_at ?? now();
        $progress->active_seconds = ($progress->active_seconds ?? 0) + $activeDelta;
        $progress->save();

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
