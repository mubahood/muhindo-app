<?php

namespace App\Listeners\Learning;

use App\Events\Learning\CourseCompleted;
use App\Notifications\CourseCompletedNotification;
use App\Services\Learning\CertificateService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Certificate issuance (gated: all lessons *and* any
 * counts_toward_certificate quiz requirement) + the completion email,
 * decoupled from ProgressService's completion write. Both live in one
 * listener (rather than two registered on the same event) so the order is
 * guaranteed: the certificate must exist before the email is built, since
 * its "view your certificate" action links to it. Laravel doesn't
 * guarantee execution order across independently auto-discovered listeners
 * on the same event. If a quiz gate is still unmet, no certificate is
 * issued yet, IssueCertificateWhenQuizRequirementIsMet picks it up once
 * the gating quiz is later graded.
 */
class HandleCourseCompletion implements ShouldQueue
{
    public function __construct(private readonly CertificateService $certificates) {}

    public function handle(CourseCompleted $event): void
    {
        $this->certificates->issueIfEligible($event->enrollment);

        $event->enrollment->user->notify(
            new CourseCompletedNotification($event->enrollment->fresh(['certificate', 'course'])),
        );
    }
}
