<?php

namespace App\Models;

use App\Enums\CourseProgression;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string $price
 * @property-read int|null $lessons_count present only when loaded via withCount('lessons')
 */
class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'title', 'slug', 'description', 'cover_image', 'price',
        'currency', 'level', 'category', 'is_published', 'created_by', 'progression',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
            'progression' => CourseProgression::class,
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
        return $this->hasManyThrough(Lesson::class, CourseModule::class)
            ->whereNull('course_modules.deleted_at');
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

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function lessonCount(): int
    {
        return $this->lessons()->count();
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
