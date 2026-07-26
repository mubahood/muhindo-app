<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollment extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'status', 'source', 'enrolled_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function progressRecords(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }

    public function progressPercent(): int
    {
        $total = $this->course->lessonCount();
        if ($total === 0) {
            return 0;
        }

        $done = $this->progressRecords()->whereNotNull('completed_at')->count();

        return (int) round(($done / $total) * 100);
    }
}
