<?php

namespace App\Services\Learning;

use App\Enums\LearningEventType;
use App\Models\Enrollment;
use App\Models\LearningEvent;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Model;

/** The single funnel for every `learning_events` row, the truth layer under the fast-path columns. */
class LearningEventRecorder
{
    public function record(
        Enrollment $enrollment,
        LearningEventType $event,
        ?Lesson $lesson = null,
        ?Model $subject = null,
        array $value = [],
    ): LearningEvent {
        return LearningEvent::create([
            'enrollment_id' => $enrollment->id,
            'lesson_id' => $lesson?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event,
            'value' => $value,
        ]);
    }
}
