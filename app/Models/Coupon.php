<?php

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string $value
 */
class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'max_uses', 'used_count', 'expires_at', 'course_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at);
    }

    public function hasUsesRemaining(): bool
    {
        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    public function appliesTo(Course $course): bool
    {
        return $this->course_id === null || $this->course_id === $course->id;
    }
}
