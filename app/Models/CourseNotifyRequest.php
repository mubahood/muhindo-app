<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody who wanted a course that was not on sale yet.
 *
 * The most valuable row this site produces: a person who arrived, read a sales
 * page, decided yes, and found nothing to buy. Before this table existed they
 * left and there was no record they had ever been interested.
 */
class CourseNotifyRequest extends Model
{
    protected $fillable = [
        'course_id', 'user_id', 'name', 'whatsapp', 'email',
        'notified_at', 'ip', 'source_path',
    ];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<$this> $query */
    public function scopeWaiting(Builder $query): void
    {
        $query->whereNull('notified_at');
    }

    /**
     * A Ugandan number in the shape WhatsApp itself wants: digits only, with
     * the country code and no leading zero.
     *
     * 0783204665, +256 783 204 665 and 256783204665 are the same person typing
     * the same number three ways, and a list that treats them as three people
     * is a list nobody can message.
     */
    public static function normaliseWhatsApp(string $raw, string $default = '256'): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        // 0783204665 -> 256783204665
        if (str_starts_with($digits, '0')) {
            return $default.ltrim($digits, '0');
        }

        // 783204665 -> 256783204665, but only for a local-length number, so a
        // foreign number that happens to start with 7 is left alone.
        if (strlen($digits) === 9 && ! str_starts_with($digits, $default)) {
            return $default.$digits;
        }

        return $digits;
    }
}
