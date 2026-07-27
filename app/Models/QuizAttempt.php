<?php

namespace App\Models;

use App\Enums\QuizAttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuizAttempt extends Model
{
    use LogsActivity;

    protected $fillable = [
        'uuid', 'quiz_id', 'enrollment_id', 'attempt_no', 'status', 'started_at',
        'submitted_at', 'graded_at', 'score_points', 'max_points', 'score_percent',
        'passed', 'time_spent_seconds', 'question_order', 'integrity',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuizAttemptStatus::class,
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'score_points' => 'decimal:2',
            'max_points' => 'decimal:2',
            'score_percent' => 'decimal:2',
            'passed' => 'boolean',
            'question_order' => 'array',
            'integrity' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Quiz, $this> */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    /** §9 — grades are auditable: the score/pass outcome and the status transition that produced it, not every autosaved answer. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'score_percent', 'passed'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('grading');
    }
}
