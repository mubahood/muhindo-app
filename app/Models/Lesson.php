<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = [
        'course_module_id', 'title', 'content', 'video_url',
        'duration_minutes', 'sort_order', 'is_free_preview',
    ];

    protected function casts(): array
    {
        return ['is_free_preview' => 'boolean'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class);
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function course(): ?Course
    {
        return $this->module?->course;
    }
}
