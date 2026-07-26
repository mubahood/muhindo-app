<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read int|null $completed_lessons_count present only when loaded via the
 *                          `progressRecords as completed_lessons_count` withCount alias
 */
class Enrollment extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'status', 'source', 'enrolled_at', 'completed_at',
        'progress_percent', 'total_watch_seconds', 'last_lesson_id', 'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    /** @return HasMany<LearningEvent, $this> */
    public function learningEvents(): HasMany
    {
        return $this->hasMany(LearningEvent::class);
    }

    /** @return HasMany<EnrollmentNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(EnrollmentNote::class)->latest();
    }

    /**
     * List views should eager-load counts to avoid an N+1 here: `->with(['course' =>
     * fn ($q) => $q->withCount('lessons')])` on the enrollment query plus
     * `->withCount(['progressRecords as completed_lessons_count' => fn ($q) =>
     * $q->whereNotNull('completed_at')])`. When those aren't present (e.g. a single
     * enrollment just mutated in a controller action) this falls back to live counts.
     */
    public function progressPercent(): int
    {
        $total = $this->course->lessons_count ?? $this->course->lessonCount();
        if ($total === 0) {
            return 0;
        }

        $done = $this->completed_lessons_count ?? $this->progressRecords()->whereNotNull('completed_at')->count();

        return (int) round(($done / $total) * 100);
    }
}
