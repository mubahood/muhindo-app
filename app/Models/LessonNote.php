<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonNote extends Model
{
    protected $fillable = ['enrollment_id', 'lesson_id', 'seconds', 'body'];

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

    public function formattedTime(): ?string
    {
        if ($this->seconds === null) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($this->seconds, 60), $this->seconds % 60);
    }
}
