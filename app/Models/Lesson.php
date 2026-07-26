<?php

namespace App\Models;

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_module_id', 'title', 'content', 'video_url',
        'duration_minutes', 'sort_order', 'is_free_preview',
        'completion_rule', 'completion_threshold', 'content_format',
    ];

    protected function casts(): array
    {
        return [
            'is_free_preview' => 'boolean',
            'completion_rule' => CompletionRule::class,
            'content_format' => ContentFormat::class,
        ];
    }

    /** Seconds are what telemetry/thresholds actually work in — derived, not stored, to avoid a second column that can drift from duration_minutes. */
    public function durationSeconds(): ?int
    {
        return $this->duration_minutes !== null ? $this->duration_minutes * 60 : null;
    }

    /** §7.3 — the YouTube IFrame API needs a bare video id, not the embed URL admins paste in. Null for non-YouTube URLs (Vimeo, etc.), which fall back to a plain iframe. */
    public function youtubeVideoId(): ?string
    {
        if (! $this->video_url) {
            return null;
        }

        if (preg_match('~(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $this->video_url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /** @return BelongsTo<CourseModule, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /** @return HasMany<LessonMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(LessonMaterial::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function course(): ?Course
    {
        return $this->module?->course;
    }
}
