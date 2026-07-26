<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    public function lessons(): HasMany
    {
        return $this->hasManyThrough(Lesson::class, CourseModule::class);
    }

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
        return $this->modules->sum(fn (CourseModule $m) => $m->lessons->count());
    }
}
