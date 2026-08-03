<?php

namespace App\Models;

use App\Enums\CourseProgression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $price
 * @property numeric-string|null $price_usd
 * @property-read int|null $lessons_count present only when loaded via withCount('lessons')
 * @property-read int|null $lessons_sum_duration_minutes present only when loaded via withSum('lessons', 'duration_minutes')
 */
class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'title', 'slug', 'description', 'tagline', 'outcomes', 'requirements',
        'cover_image', 'cover_alt', 'price',
        'currency', 'level', 'category', 'is_published', 'created_by', 'progression', 'debug_mode',
        'price_usd', 'course_number', 'tier', 'is_featured', 'prerequisites_note', 'playlist_url', 'source_file', 'synced_at',
        'access_duration_days',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
            'progression' => CourseProgression::class,
            'debug_mode' => 'boolean',
            'is_featured' => 'boolean',
            'price_usd' => 'decimal:2',
            'synced_at' => 'datetime',
            'outcomes' => 'array',
            'requirements' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CourseModule, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    /** @return HasManyThrough<Lesson, CourseModule, $this> */
    public function lessons(): HasManyThrough
    {
        // hasManyThrough does not apply the intermediate model's own soft-delete
        // scope automatically, so a soft-deleted module's lessons must be excluded
        // explicitly to keep lessonCount() (and therefore progressPercent()) correct.
        // Unpublished (draft) lessons are excluded too — §7.5's publish toggle means a
        // draft doesn't count toward a course's structure from the student's side at all
        // (not "locked," genuinely doesn't exist yet), so it can't block 100% completion.
        return $this->hasManyThrough(Lesson::class, CourseModule::class)
            ->whereNull('course_modules.deleted_at')
            ->where('lessons.is_published', true);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** @return HasMany<Quiz, $this> */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<Announcement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class)->latest();
    }

    /** @return HasMany<Discussion, $this> */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    /** @return HasMany<CourseReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    /** §4.4/§6.4 — the access-window expiry to stamp on a newly-activated enrollment; null (lifetime access) unless the course sets a duration. */
    public function enrollmentExpiresAt(): ?Carbon
    {
        return $this->access_duration_days ? now()->addDays($this->access_duration_days) : null;
    }

    public function lessonCount(): int
    {
        return $this->lessons()->count();
    }

    /** §2.2 — the one-line card hook; falls back to a trimmed description so an unset tagline never shows blank space. */
    /**
     * The description a search engine should index: the whole thing, with
     * markdown stripped but nothing truncated.
     *
     * cardTagline() cuts at 110 characters for a card, and a schema
     * description ending in "..." is what Google would show. These are two
     * different jobs and were sharing one method.
     */
    public function seoDescription(): string
    {
        $plain = $this->tagline ?: (string) $this->description;
        $plain = preg_replace('/\*\*(.+?)\*\*/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/`(.+?)`/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }

    /**
     * The description, rendered.
     *
     * cardTagline() and seoDescription() both strip markdown because their
     * destinations are plain text. The detail page is the one place with room
     * to show it as written, and it was printing the asterisks instead — the
     * author's **bold** arriving on screen as literal punctuation.
     */
    public function descriptionHtml(): string
    {
        if (! $this->description) {
            return '';
        }

        /*
         * The import hard-wrapped these at about 85 characters and left blank
         * lines at the wrap points, so markdown reads one sentence as three
         * paragraphs. A break whose next line starts lower-case is a wrap, not
         * a paragraph — joined back. A break after a full stop is left alone.
         */
        $text = preg_replace('/\n\s*\n(?=\p{Ll})/u', ' ', (string) $this->description);
        $text = preg_replace('/(?<!\n)\n(?!\n)/u', ' ', (string) $text);

        return app(\App\Services\Learning\MarkdownRenderer::class)->toHtml((string) $text);
    }

    public function cardTagline(): string
    {
        if ($this->tagline) {
            return $this->tagline;
        }

        /*
         * Descriptions are markdown — the imported catalogue is full of **bold**
         * and *emphasis* — and a card renders plain text, so the raw asterisks
         * were showing up on screen. Strip the inline marks rather than render
         * HTML into a place that has no room for it.
         */
        $plain = (string) $this->description;
        $plain = preg_replace('/\*\*(.+?)\*\*/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/`(.+?)`/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return \Illuminate\Support\Str::limit(trim($plain), 110);
    }

    /** §2.3/§6.5 — image alt text; falls back to the course title so a cover is never missing alt text. */
    public function coverAlt(): string
    {
        return $this->cover_alt ?: $this->title;
    }

    /**
     * §4.3 — in `sequential` progression, a lesson is locked until the one
     * immediately before it (in module/lesson sort order) is completed. The
     * first lesson is never locked. `free` progression never locks anything.
     */
    public function isLessonLocked(Enrollment $enrollment, Lesson $lesson): bool
    {
        if ($this->progression !== CourseProgression::Sequential) {
            return false;
        }

        $ordered = $this->modules->flatMap(fn (CourseModule $module) => $module->lessons);
        $index = $ordered->search(fn (Lesson $candidate) => $candidate->id === $lesson->id);
        if ($index === false || $index === 0) {
            return false;
        }

        $previous = $ordered->get($index - 1);

        return ! $enrollment->progressRecords()
            ->where('lesson_id', $previous->id)
            ->whereNotNull('completed_at')
            ->exists();
    }
}
