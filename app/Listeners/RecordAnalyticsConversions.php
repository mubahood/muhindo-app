<?php

namespace App\Listeners;

use App\Models\Certificate;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\ProductLicense;
use App\Models\ProjectInquiry;
use App\Services\Analytics\Tracker;
use App\Support\Analytics\Events;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Events\Dispatcher;

/**
 * Conversions, recorded from the models rather than from the controllers.
 *
 * Every one of these outcomes already has more than one way to happen. An
 * enrollment is created by self-enrolment on a free course, by an admin
 * enrolling somebody by hand, by bulk enrolment from a spreadsheet and by a
 * paid checkout completing. Instrumenting the controllers means four calls
 * that must all be remembered, and the day a fifth path is added the reports
 * quietly go wrong with nothing to show that they have.
 *
 * Listening to the model instead makes the row itself the trigger, so a
 * conversion is recorded exactly when one really happened, once, whatever
 * caused it. That is also what lets this module be added to a finished
 * application without editing a single existing controller.
 *
 * The cost is that "who did it" is not always the signed-in user (an admin
 * enrolling a student is not the student), so each subscriber passes the
 * subject's own owner explicitly rather than trusting the session.
 */
class RecordAnalyticsConversions
{
    public function __construct(private readonly Tracker $tracker) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Registered::class, [self::class, 'onRegistered']);
        $events->listen(Login::class, [self::class, 'onLogin']);
        $events->listen(Logout::class, [self::class, 'onLogout']);

        $events->listen('eloquent.created: '.Enrollment::class, [self::class, 'onEnrolled']);
        $events->listen('eloquent.created: '.Payment::class, [self::class, 'onPayment']);
        $events->listen('eloquent.created: '.ProductLicense::class, [self::class, 'onLicensed']);
        $events->listen('eloquent.created: '.ProjectInquiry::class, [self::class, 'onInquiry']);
        $events->listen('eloquent.created: '.ContactMessage::class, [self::class, 'onContact']);
        $events->listen('eloquent.created: '.Certificate::class, [self::class, 'onCertificate']);
        $events->listen('eloquent.updated: '.Enrollment::class, [self::class, 'onEnrollmentUpdated']);
    }

    public function onRegistered(Registered $event): void
    {
        $user = $event->user;

        // The account is what ties this browser's whole anonymous history to a
        // name, so identify first and record second.
        if ($visitor = $this->tracker->resolveVisitor($user)) {
            $this->tracker->identify($visitor, $user);
        }

        $this->tracker->event(
            name: Events::SIGNUP,
            subject: $user,
            label: $user->name,
            meta: ['role' => $user->role, 'types' => $user->accountTypes()],
            user: $user,
        );
    }

    public function onLogin(Login $event): void
    {
        if ($visitor = $this->tracker->resolveVisitor($event->user)) {
            $this->tracker->identify($visitor, $event->user);
        }

        $this->tracker->event(name: Events::LOGIN, label: $event->user->name, user: $event->user);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user) {
            $this->tracker->event(name: Events::LOGOUT, label: $event->user->name, user: $event->user);
        }
    }

    public function onEnrolled(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('course', 'user');

        $this->tracker->event(
            name: Events::ENROLLED,
            subject: $enrollment->course,
            label: $enrollment->course?->title,
            meta: ['source' => $enrollment->source, 'status' => $enrollment->status],
            user: $enrollment->user,
        );
    }

    /**
     * A payment, not an invoice reaching "paid". Part-payments are normal here
     * (mobile money in two halves), and each one is money that arrived on a
     * day, which is what a revenue chart is actually made of.
     */
    public function onPayment(Payment $payment): void
    {
        $payment->loadMissing('invoice.billable');
        $invoice = $payment->invoice;
        $billable = $invoice?->billable;

        $this->tracker->event(
            name: Events::ORDER_PAID,
            subject: $invoice,
            label: $invoice?->invoice_no,
            value: (float) $payment->amount,
            currency: $invoice?->currency,
            meta: [
                'method' => $payment->method?->value,
                'settles_invoice' => (float) $payment->balance_after <= 0,
            ],
            user: $billable instanceof \App\Models\User ? $billable : ($billable?->user ?? null),
        );
    }

    public function onLicensed(ProductLicense $license): void
    {
        $license->loadMissing('product', 'user');

        $this->tracker->event(
            name: Events::DOWNLOAD,
            subject: $license->product,
            label: $license->product?->name,
            user: $license->user,
        );
    }

    public function onInquiry(ProjectInquiry $inquiry): void
    {
        $this->tracker->event(
            name: Events::INQUIRY,
            subject: $inquiry,
            label: $inquiry->name,
            meta: [
                'project_type' => $inquiry->project_type,
                'budget' => $inquiry->budget_range,
                'timeline' => $inquiry->timeline,
            ],
        );
    }

    public function onContact(ContactMessage $message): void
    {
        $this->tracker->event(
            name: Events::CONTACT,
            subject: $message,
            label: $message->subject ?: $message->name,
        );
    }

    public function onCertificate(Certificate $certificate): void
    {
        $certificate->loadMissing('enrollment.course', 'enrollment.user');

        $this->tracker->event(
            name: Events::CERTIFICATE_EARNED,
            subject: $certificate->enrollment?->course,
            label: $certificate->enrollment?->course?->title,
            user: $certificate->enrollment?->user,
        );
    }

    /** Completion is a status change, not a row, so it is caught on update. */
    public function onEnrollmentUpdated(Enrollment $enrollment): void
    {
        if (! $enrollment->wasChanged('completed_at') || $enrollment->completed_at === null) {
            return;
        }

        $enrollment->loadMissing('course', 'user');

        $this->tracker->event(
            name: Events::COURSE_COMPLETE,
            subject: $enrollment->course,
            label: $enrollment->course?->title,
            user: $enrollment->user,
        );
    }
}
