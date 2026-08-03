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
        'is_external', 'is_embeddable', 'resource_url',
        'course_module_id', 'title', 'content', 'video_url', 'video_disk_path', 'captions_url',
        'duration_minutes', 'min_active_seconds', 'sort_order', 'is_published', 'is_free_preview',
        'completion_rule', 'completion_threshold', 'content_format',
    ];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
            'is_embeddable' => 'boolean',
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

    /**
     * Static so the admin curriculum builder can resolve a pasted URL before
     * any Lesson exists.
     *
     * youtube-nocookie.com has to match: it is what the privacy-preserving
     * embed uses, and it is what the whole imported catalogue is written in.
     * Without it every one of those lessons fell back to a plain iframe, so
     * the IFrame API never loaded and no watch progress was ever recorded.
     */
    public static function extractYoutubeId(string $url): ?string
    {
        $pattern = '~(?:youtube(?:-nocookie)?\.com/(?:embed/|v/|shorts/|watch\?(?:.*&)?v=)'
            .'|youtu\.be/)([A-Za-z0-9_-]{6,})~';

        return preg_match($pattern, $url, $matches) ? $matches[1] : null;
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
