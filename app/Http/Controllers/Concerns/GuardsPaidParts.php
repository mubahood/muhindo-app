<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PaymentRequiredException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Support\Catalog\InstalmentPlan;

/**
 * Keeps quizzes and assignments behind the same instalment wall as the lessons
 * they belong to.
 *
 * Lessons are gated by Course::isLessonLocked(), which ProgressService and the
 * material and video controllers all authorize through LessonPolicy. Quizzes
 * and assignments hang off a course rather than a lesson, so they never passed
 * through that gate: a student who had paid for the first quarter could open
 * the last quarter's quiz by its URL and read every question in it.
 */
trait GuardsPaidParts
{
    /**
     * @param  int|null  $lessonId  the lesson the activity belongs to
     */
    protected function guardPaidPart(Course $course, Enrollment $enrollment, ?int $lessonId): void
    {
        if (! $course->allowsInstalments()) {
            return;
        }

        /*
         * An activity with no lesson belongs to the course as a whole, so it
         * is treated as part of the last instalment. A final exam is the end
         * of the course, and the money-safe reading is the right one: the
         * alternative leaves course-wide assessments open to somebody who has
         * paid a quarter of the price.
         */
        $part = InstalmentPlan::partCount();

        if ($lessonId !== null && ($lesson = Lesson::find($lessonId)) !== null) {
            $part = $course->instalmentPlan()->partOfLesson($lesson);
        }

        if (! $course->isPartLockedFor($enrollment, $part)) {
            return;
        }

        $invoice = $enrollment->invoice_id ? Invoice::with('items')->find($enrollment->invoice_id) : null;

        // No invoice to send them to; nothing to do but refuse.
        abort_if($invoice === null, 403);

        $context = InstalmentPlan::contextFor($invoice);

        throw new PaymentRequiredException(
            $invoice,
            $context !== null && bccomp($context['next_amount'], '0', 2) > 0
                ? sprintf(
                    'That is in %s of this course. Pay %s %s to open it.',
                    InstalmentPlan::partName($part),
                    $invoice->currency,
                    number_format((float) $context['next_amount']),
                )
                : 'That part of the course has not been paid for yet.',
        );
    }
}
