<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'instructions', 'due_at', 'points',
        'allow_late', 'late_penalty_percent', 'max_file_mb', 'allowed_types',
        'resubmit_until_graded', 'is_published',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'allow_late' => 'boolean',
            'resubmit_until_graded' => 'boolean',
            'is_published' => 'boolean',
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

    /** @return HasMany<AssignmentSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && now()->gt($this->due_at);
    }

    /** @return list<string> */
    public function allowedTypes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $this->allowed_types ?? ''))));
    }

    public function acceptsType(string $type): bool
    {
        return in_array($type, $this->allowedTypes(), true);
    }

    /** Whether any *file* extension (as opposed to the special 'text'/'link' modes) is allowed. */
    public function acceptsAnyFileType(): bool
    {
        return array_diff($this->allowedTypes(), ['text', 'link']) !== [];
    }
}
