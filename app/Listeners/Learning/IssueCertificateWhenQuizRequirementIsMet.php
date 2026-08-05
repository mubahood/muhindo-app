<?php

namespace App\Listeners\Learning;

use App\Events\Learning\QuizAttemptSubmitted;
use App\Notifications\CourseCompletedNotification;
use App\Services\Learning\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * A student can finish every lesson before passing the gating quiz, in which case
 * HandleCourseCompletion's issueIfEligible() call comes back empty. This is the other trigger:
 * once any quiz attempt is graded, re-check whether the certificate is now earned. A no-op if
 * lessons aren't done yet, the quiz doesn't count toward the certificate, the requirement still
 * isn't met, or a certificate already exists (issue() itself is idempotent regardless).
 */
class IssueCertificateWhenQuizRequirementIsMet implements ShouldQueue
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function handle(QuizAttemptSubmitted $event): void
    {
        $enrollment = $event->attempt->enrollment;

        if ($enrollment->certificate()->exists()) {
            return;
        }

        $certificate = $this->certificates->issueIfEligible($enrollment);

        if ($certificate) {
            $enrollment->user->notify(new CourseCompletedNotification($enrollment->fresh(['certificate', 'course'])));
        }
    }
}
