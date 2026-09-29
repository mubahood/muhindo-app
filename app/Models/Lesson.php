<?php

namespace App\Models;

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use App\Enums\LessonStage;
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

    /** Seconds are what telemetry/thresholds actually work in, derived, not stored, to avoid a second column that can drift from duration_minutes. */
    public function durationSeconds(): ?int
    {
        return $this->duration_minutes !== null ? $this->duration_minutes * 60 : null;
    }

    /** A self-hosted video takes priority over a YouTube/Vimeo `video_url` when both happen to be set. */
    public function hasSelfHostedVideo(): bool
    {
        return filled($this->video_disk_path);
    }

    /** The YouTube IFrame API needs a bare video id, not the embed URL admins paste in. Null for non-YouTube URLs (Vimeo, etc.), which fall back to a plain iframe. */
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

    /* Draft state ---------------------------------------------------------
     *
     * `is_published` is the only bit stored. Everything a human wants to know
     * about where a topic has got to is worked out from it and from what the
     * row actually holds, so the two can never disagree.
     */

    /**
     * Nothing at all: no recording and not a word of text.
     *
     * Distinct from the Unrecorded stage, which is about the missing video. A
     * topic can be deliberately written rather than filmed; this is the one
     * that is neither, and it is the harsher of the two warnings.
     */
    public function isBlank(): bool
    {
        return ! $this->hasVideo() && trim((string) $this->content) === '';
    }

    public function hasVideo(): bool
    {
        return filled($this->video_url) || filled($this->video_disk_path);
    }

    public function stage(): LessonStage
    {
        if ($this->hasVideo()) {
            return $this->is_published ? LessonStage::Live : LessonStage::Ready;
        }

        return $this->is_published ? LessonStage::Unrecorded : LessonStage::Planned;
    }

    /** Recorded and waiting: the only thing a bulk publish is allowed to touch. */
    public function isReadyToPublish(): bool
    {
        return $this->stage() === LessonStage::Ready;
    }

    public function publish(): bool
    {
        return $this->is_published ? false : $this->forceFill(['is_published' => true])->save();
    }

    public function unpublish(): bool
    {
        return $this->is_published ? $this->forceFill(['is_published' => false])->save() : false;
    }

    /**
     * Attach a recording.
     *
     * Both URL forms are written from the one the author pasted, whichever it
     * was: the player iframes `video_url` and so needs the embed form, while
     * `resource_url` is the canonical watch page, kept for the student who
     * would rather open it on YouTube and for the case where the video turns
     * out not to be embeddable at all.
     *
     * youtube-nocookie is not decoration. It is what the entire imported
     * catalogue is written in, and what `extractYoutubeId()` is built to
     * match; a plain youtube.com embed would leave the IFrame API unloaded and
     * silently record no watch progress for that lesson.
     */
    public function attachVideo(string $url): void
    {
        $url = trim($url);
        $id = self::extractYoutubeId($url);

        if ($id !== null) {
            $this->video_url = 'https://www.youtube-nocookie.com/embed/'.$id;
            $this->resource_url = 'https://www.youtube.com/watch?v='.$id;

            return;
        }

        // Vimeo and the rest: keep what was pasted. It plays in a plain iframe.
        $this->video_url = $url;
    }

    public function detachVideo(): void
    {
        $this->video_url = null;
        $this->resource_url = null;
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
