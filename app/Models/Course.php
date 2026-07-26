<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property numeric-string $price
 */
class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'title', 'slug', 'description', 'cover_image', 'price',
        'currency', 'level', 'category', 'is_published', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_published' => 'boolean',
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

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function lessonCount(): int
    {
        return $this->lessons()->count();
    }
}
