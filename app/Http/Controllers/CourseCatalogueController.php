<?php

namespace App\Http\Controllers;

use App\Enums\ContentFormat;
use App\Events\Learning\EnrollmentCreated;
use App\Exceptions\InvalidCouponException;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Lesson;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Learning\MarkdownRenderer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseCatalogueController extends Controller
{
    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly BillingService $billing,
    ) {}

    public function index(): View
    {
        return view('courses.index', [
            'courses' => Course::where('is_published', true)
                ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_published', true)])
                ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('is_published', true)], 'rating')
                ->latest()->get(),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless($course->is_published || auth()->user()?->isAdmin(), 404);

        $existing = auth()->check()
            ? Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->first()
            : null;

        $course->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('is_published', true)])
            ->loadAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('is_published', true)], 'rating');

        return view('courses.show', [
            'course' => $course->load('modules.lessons'),
            'enrollment' => $existing && in_array($existing->status, ['active', 'completed'], true) ? $existing : null,
            'pendingCheckout' => $existing?->status === 'pending',
            'publishedReviews' => $course->reviews()->where('is_published', true)->with('enrollment.user')->latest()->get(),
        ]);
    }

    /** §7.2 — free preview: a guest (no enrollment) can view an is_free_preview lesson, closing L5. */
    public function preview(Course $course, Lesson $lesson): View
    {
        abort_unless($course->is_published, 404);
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($lesson->is_free_preview && $lesson->is_published, 404);

        $renderedContent = $lesson->content && $lesson->content_format === ContentFormat::Markdown
            ? $this->markdown->toHtml($lesson->content)
            : null;

        return view('courses.preview', [
            'course' => $course->load('modules.lessons'),
            'lesson' => $lesson,
            'renderedContent' => $renderedContent,
        ]);
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();
        $existing = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();

        if ($existing && in_array($existing->status, ['active', 'completed'], true)) {
            return redirect()->route('learn.course', $course)->with('success', 'You are already enrolled in this course.');
        }

        if ($course->isFree()) {
            try {
                $enrollment = Enrollment::firstOrCreate(
                    ['user_id' => $user->id, 'course_id' => $course->id],
                    ['uuid' => (string) Str::uuid(), 'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => $course->enrollmentExpiresAt()],
                );
                if ($enrollment->wasRecentlyCreated) {
                    EnrollmentCreated::dispatch($enrollment);
                }
            } catch (UniqueConstraintViolationException) {
                // A concurrent request (double-click) won the race — the enrollment exists either way, no new event.
            }

            return redirect()->route('learn.course', $course)->with('success', 'You are enrolled — happy learning!');
        }

        // §7.1 — a paid course: a pending enrollment already sitting on an unpaid invoice
        // just needs to be paid, not re-invoiced.
        if ($existing && $existing->status === 'pending' && $existing->invoice_id) {
            return redirect()->route('courses.checkout', $course);
        }

        $couponCode = $request->filled('coupon_code') ? trim((string) $request->string('coupon_code')) : null;

        try {
            $invoice = $this->billing->generateCourseInvoice($user, $course, $couponCode);
        } catch (InvalidCouponException $e) {
            return redirect()->route('courses.show', $course)->with('error', $e->getMessage());
        }

        if ($existing) {
            // A previously cancelled enrollment is reactivated as pending against the new invoice.
            $existing->update(['status' => 'pending', 'invoice_id' => $invoice->id, 'enrolled_at' => null]);
        } else {
            try {
                Enrollment::create([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'invoice_id' => $invoice->id,
                    'status' => 'pending',
                    'source' => 'self',
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request already created the pending enrollment — the invoice we
                // just generated is simply unused; the checkout page below finds the real one.
            }
        }

        return redirect()->route('courses.checkout', $course);
    }

    /** §7.1 — shows the pending invoice for a paid course and a "Pay with Flutterwave" button. */
    public function checkout(Request $request, Course $course): View|RedirectResponse
    {
        $user = $request->user();
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->first();

        if (! $enrollment || ! $enrollment->invoice_id) {
            return redirect()->route('courses.show', $course);
        }

        $invoice = Invoice::findOrFail($enrollment->invoice_id);
        abort_unless($invoice->billable_type === User::class && $invoice->billable_id === $user->id, 403);

        return view('courses.checkout', ['course' => $course, 'invoice' => $invoice]);
    }
}
