<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Learning\EnrollmentAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentAdminService $enrollments) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'course_id' => (string) $request->query('course_id', ''),
            'status' => (string) $request->query('status', ''),
            'source' => (string) $request->query('source', ''),
            'billing' => (string) $request->query('billing', ''),
        ];

        $query = Enrollment::with(['user', 'course', 'invoice'])
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                // One box for "who": name or email, because whoever is looking
                // for a person has whichever of the two they were given.
                $term = '%'.$filters['q'].'%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($filters['course_id'] !== '', fn ($q) => $q->where('course_id', $filters['course_id']))
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['source'] !== '', fn ($q) => $q->where('source', $filters['source']))
            ->when($filters['billing'] === 'unpaid', fn ($q) => $q->whereHas('invoice',
                fn ($i) => $i->whereIn('status', ['issued', 'partially_paid'])->where('balance', '>', 0)))
            ->when($filters['billing'] === 'uninvoiced', fn ($q) => $q->whereNull('invoice_id'))
            ->when($filters['billing'] === 'direct', fn ($q) => $q->whereHas('invoice',
                fn ($i) => $i->whereNotNull('direct_payment_at')));

        return view('admin.enrollments.index', [
            'enrollments' => $query->latest('id')->paginate(30)->withQueryString(),
            'students' => User::where('is_student', true)->orWhere('role', 'student')->orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(),
            'filters' => $filters,
            'statuses' => EnrollmentAdminService::STATUSES,
            'counts' => [
                'all' => Enrollment::count(),
                'pending' => Enrollment::where('status', 'pending')->count(),
                'active' => Enrollment::where('status', 'active')->count(),
                'unpaid' => Enrollment::whereHas('invoice',
                    fn ($i) => $i->whereIn('status', ['issued', 'partially_paid'])->where('balance', '>', 0))->count(),
            ],
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'status' => 'nullable|in:pending,active',
        ]);

        $existing = Enrollment::where('user_id', $data['user_id'])->where('course_id', $course->id)->first();
        if ($existing) {
            return back()->with('error', 'That student is already enrolled in this course.');
        }

        $this->enrollments->enrol($course, (int) $data['user_id'], $data['status'] ?? 'active');

        return back()->with('success', 'Student enrolled.');
    }

    /** Change status, and optionally the access window, in one submission. */
    public function update(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', array_keys(EnrollmentAdminService::STATUSES)),
            'expires_at' => 'nullable|date',
            'clear_expiry' => 'nullable|boolean',
        ]);

        try {
            $note = $this->enrollments->setStatus($enrollment, $data['status'], $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Applied after the status, because activating fills a null expiry from
        // the course default and an explicit choice here has to win over that.
        if ($request->boolean('clear_expiry')) {
            $enrollment->update(['expires_at' => null]);
        } elseif (! empty($data['expires_at'])) {
            $enrollment->update(['expires_at' => $data['expires_at']]);
        }

        return back()->with('success', $note);
    }

    /** Raise an invoice for an enrollment that has none. */
    public function invoice(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['coupon_code' => 'nullable|string|max:64']);

        try {
            $invoice = $this->enrollments->createInvoice(
                $enrollment,
                $data['coupon_code'] ?? null,
                $request->user()->id,
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf(
            'Invoice %s raised for %s %s. The payment link is now on this row — send it to the student.',
            $invoice->invoice_no,
            $invoice->currency,
            number_format((float) $invoice->total, 2),
        ));
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        // The invoice deliberately outlives the enrollment. Removing access
        // must not quietly erase a debt, or a payment already made against it:
        // that record belongs to billing, not to access.
        $invoiceNote = $enrollment->invoice_id !== null
            && Invoice::find($enrollment->invoice_id)?->isOutstanding()
                ? ' Its unpaid invoice is untouched and still in Billing.'
                : '';

        $enrollment->delete();

        return back()->with('success', 'Enrollment removed.'.$invoiceNote);
    }
}
