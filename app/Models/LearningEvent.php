<?php

namespace App\Models;

use App\Enums\LearningEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only xAPI-lite event stream, never updated, only pruned (P5). */
class LearningEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['enrollment_id', 'lesson_id', 'subject_type', 'subject_id', 'event', 'value'];

    protected function casts(): array
    {
        return [
            'event' => LearningEventType::class,
            'value' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
