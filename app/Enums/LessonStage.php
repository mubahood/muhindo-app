<?php

namespace App\Enums;

/**
 * Where a topic has got to, between being written and being watchable.
 *
 * Derived, never stored. `lessons.is_published` remains the only bit on the
 * row; this reads it alongside whether a recording is attached. A stored
 * status column would need a backfill and a sync job, and would be wrong the
 * first time somebody cleared a video URL without remembering to change it.
 *
 * The dividing line is the video and not the content, because in this
 * catalogue the video is the lesson. Text was measured before this was
 * decided: the median topic with no video carries 71 characters, which is the
 * one-line description the import wrote from the course file, while topics
 * that do have a video mostly carry no text at all. Treating that sentence as
 * content would have marked 140 unrecorded topics as finished.
 *
 * Unrecorded is not a step in the workflow. It is a fault: a topic a student
 * can open with nothing to watch in it. It can be detected and never set,
 * which is the reason it is in this list rather than in a comment somewhere.
 */
enum LessonStage: string
{
    case Planned = 'planned';
    case Ready = 'ready';
    case Live = 'live';
    case Unrecorded = 'unrecorded';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Needs recording',
            self::Ready => 'Ready to publish',
            self::Live => 'Live',
            self::Unrecorded => 'Live, no video',
        };
    }

    /** The one-line answer to "so what do I do about it?" */
    public function nextStep(): string
    {
        return match ($this) {
            self::Planned => 'Record the video, then paste the link here.',
            self::Ready => 'The video is attached. Publish it whenever you like.',
            self::Live => 'Nothing. Students can watch this.',
            self::Unrecorded => 'Students can open this and there is nothing to watch. Record it, or take it down.',
        };
    }

    /** Maps onto the admin theme's existing badge classes; no new CSS. */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'badge-neutral',
            self::Ready => 'badge-info',
            self::Live => 'badge-active',
            self::Unrecorded => 'badge-danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Planned => 'fa-pen-ruler',
            self::Ready => 'fa-circle-play',
            self::Live => 'fa-circle-check',
            self::Unrecorded => 'fa-triangle-exclamation',
        };
    }

    /** Visible to a student right now. */
    public function isVisibleToStudents(): bool
    {
        return $this === self::Live || $this === self::Unrecorded;
    }

    /** The only stage a bulk publish is allowed to move. */
    public function canBePublishedInBulk(): bool
    {
        return $this === self::Ready;
    }

    /**
     * Ordered worst-first, which is the order the recording queue and the
     * readiness panel both want: the fault, then the work, then the done.
     *
     * @return list<self>
     */
    public static function byUrgency(): array
    {
        return [self::Unrecorded, self::Planned, self::Ready, self::Live];
    }
}
