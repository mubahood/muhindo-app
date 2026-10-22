<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Catalog\InstalmentPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property-read int|null $completed_lessons_count present only when loaded via the
 *                          `progressRecords as completed_lessons_count` withCount alias
 */
class Enrollment extends Model
{
    use LogsActivity;

    private ?int $instalmentPartsUnlocked = null;

    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'invoice_id', 'status', 'source', 'enrolled_at', 'completed_at',
        'progress_percent', 'total_watch_seconds', 'last_lesson_id', 'last_accessed_at', 'at_risk_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** A null `expires_at` means lifetime access; a past one means the access window has closed. */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * How much of the course this student has actually bought, in parts.
     *
     * Everything that is not an unfinished instalment plan returns the whole
     * course, and the order of those cases is the order they go wrong in:
     *
     * - A course not sold in parts has no parts to withhold.
     * - An enrollment with no invoice was granted, not bought. A teacher adding
     *   a student by hand, a free course, a bulk enrolment: none of them has a
     *   ledger to read, and reading one anyway would lock the student out of a
     *   course somebody deliberately gave them.
     * - A settled invoice is the whole course, whatever route the money took.
     *
     * Only then is the ledger consulted, and it is the invoice total that is
     * divided, not the course price: a coupon, a discount, or a price raised
     * after the sale must not change what the student already paid for.
     *
     * Memoised, because isLessonLocked() asks this once per lesson and a
     * twelve-module course would otherwise re-read the same invoice eighty-odd
     * times to render one sidebar.
     */
    public function instalmentPartsUnlocked(): int
    {
        if ($this->instalmentPartsUnlocked !== null) {
            return $this->instalmentPartsUnlocked;
        }

        $all = InstalmentPlan::partCount();

        $course = $this->relationLoaded('course') ? $this->course : Course::find($this->course_id);

        if ($course === null || ! $course->allowsInstalments() || $this->invoice_id === null) {
            return $this->instalmentPartsUnlocked = $all;
        }

        $invoice = $this->relationLoaded('invoice') ? $this->invoice : Invoice::find($this->invoice_id);

        if ($invoice === null || $invoice->status === InvoiceStatus::Paid) {
            return $this->instalmentPartsUnlocked = $all;
        }

        return $this->instalmentPartsUnlocked = InstalmentPlan::partsCoveredBy(
            (string) $invoice->amount_paid,
            (string) $invoice->total,
        );
    }

    /** Is this student part-way through paying for the course? */
    public function isPayingInInstalments(): bool
    {
        return $this->instalmentPartsUnlocked() < InstalmentPlan::partCount();
    }

    /** Drop the memo after a payment lands, so the next read sees the new part. */
    public function forgetInstalmentState(): void
    {
        $this->instalmentPartsUnlocked = null;
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

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

    /** @return HasMany<QuizAttempt, $this> */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return HasMany<AssignmentSubmission, $this> */
    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * The student's own private study notes, distinct from notes(), the admin's private notes
     * about this student.
     *
     * @return HasMany<LessonNote, $this>
     */
    public function lessonNotes(): HasMany
    {
        return $this->hasMany(LessonNote::class);
    }

    /** @return HasOne<CourseReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(CourseReview::class);
    }

    /** Enrollment mutations are auditable: status transitions (enrolled/activated/cancelled/completed) and access-window changes, not every denormalized progress tick. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'expires_at', 'invoice_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('enrollment');
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
