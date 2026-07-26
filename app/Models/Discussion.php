<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discussion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id', 'lesson_id', 'user_id', 'parent_id', 'body', 'is_instructor_answer', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_instructor_answer' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Discussion, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'parent_id');
    }

    /** @return HasMany<Discussion, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Discussion::class, 'parent_id')->oldest();
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }
}
