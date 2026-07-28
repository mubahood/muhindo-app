<?php

namespace App\Models;

use App\Enums\QuizFeedbackMode;
use App\Enums\QuizGradingMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quiz extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'description', 'time_limit_minutes',
        'max_attempts', 'pass_percent', 'grading_method', 'shuffle_questions',
        'shuffle_options', 'questions_per_attempt', 'one_question_per_page',
        'feedback_mode', 'counts_toward_certificate', 'is_required', 'is_published',
        'available_from', 'available_until',
    ];

    protected function casts(): array
    {
        return [
            'grading_method' => QuizGradingMethod::class,
            'feedback_mode' => QuizFeedbackMode::class,
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'one_question_per_page' => 'boolean',
            'counts_toward_certificate' => 'boolean',
            'is_required' => 'boolean',
            'is_published' => 'boolean',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
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

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('sort_order');
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isAvailableNow(): bool
    {
        $now = now();
        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }
        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }

        return true;
    }
}
