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
        'course_module_id', 'title', 'content', 'video_url', 'video_disk_path', 'captions_url',
        'duration_minutes', 'sort_order', 'is_published', 'is_free_preview',
        'completion_rule', 'completion_threshold', 'content_format',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
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

    /** §6.2/P5.3 — a self-hosted video takes priority over a YouTube/Vimeo `video_url` when both happen to be set. */
    public function hasSelfHostedVideo(): bool
    {
        return filled($this->video_disk_path);
    }

    /** §7.3 — the YouTube IFrame API needs a bare video id, not the embed URL admins paste in. Null for non-YouTube URLs (Vimeo, etc.), which fall back to a plain iframe. */
    public function youtubeVideoId(): ?string
    {
        return $this->video_url ? self::extractYoutubeId($this->video_url) : null;
    }

    /** Static so the admin curriculum builder can resolve a pasted URL before any Lesson exists. */
    public static function extractYoutubeId(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $matches)) {
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

    public function course(): ?Course
    {
        return $this->module?->course;
    }
}
