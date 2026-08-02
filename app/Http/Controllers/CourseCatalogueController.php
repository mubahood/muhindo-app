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
use App\Support\Settings;
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

    /** §2.2 — server-rendered, URL-driven filters/sort/search so listing pages stay shareable and crawlable. */
    public function index(Request $request): View
    {
        $query = Course::where('is_published', true)
            ->withCount(['reviews as reviews_count' => fn ($q) => $q->where('is_published', true)])
            ->withAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('is_published', true)], 'rating')
            ->withCount('lessons')
            ->withSum('lessons', 'duration_minutes')
            ->withCount('enrollments');

        if ($category = trim((string) $request->query('category'))) {
            $query->where('category', $category);
        }

        if ($level = trim((string) $request->query('level'))) {
            $query->where('level', $level);
        }

        if ($price = $request->query('price')) {
            $price === 'free' ? $query->where('price', 0) : $query->where('price', '>', 0);
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price'),
            'most_enrolled' => $query->orderByDesc('enrollments_count'),
            default => $query->latest(),
        };

        return view('courses.index', [
            'courses' => $query->paginate(9)->withQueryString(),
            'categories' => Course::where('is_published', true)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'totalLessonCount' => Lesson::where('is_published', true)
                ->whereHas('module', fn ($q) => $q->whereNull('deleted_at')->whereHas('course', fn ($c) => $c->where('is_published', true)))
                ->count(),
            'filters' => $request->only(['category', 'level', 'price', 'sort', 'q']),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless($course->is_published || auth()->user()?->isAdmin(), 404);

        $existing = auth()->check()
            ? Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->first()
            : null;

        $course->loadCount(['reviews as reviews_count' => fn ($q) => $q->where('is_published', true)])
            ->loadAvg(['reviews as reviews_avg_rating' => fn ($q) => $q->where('is_published', true)], 'rating')
            ->loadCount('lessons')
            ->loadSum('lessons', 'duration_minutes');

        $identity = $this->settingsJson('portfolio.identity');
        $about = $this->settingsJson('portfolio.about');
        $faq = $this->settingsJson('courses.faq');

        /* Every free-preview lesson, rendered up front and handed to the page as
           data. The modal then opens instantly with no request in flight — a
           preview that spins before it plays is a preview people abandon. Only
           lessons explicitly marked as free previews are included, so nothing
           paid can leak through the same channel. */
        $previews = $course->modules->flatMap->lessons
            ->filter(fn (Lesson $lesson) => $lesson->is_free_preview && $lesson->is_published)
            ->values()
            ->map(fn (Lesson $lesson) => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'minutes' => $lesson->duration_minutes,
                'youtube' => $lesson->youtubeVideoId(),
                'video' => $lesson->video_disk_path ? asset('storage/'.$lesson->video_disk_path) : null,
                'captions' => $lesson->captions_url,
                'html' => $lesson->content && $lesson->content_format === ContentFormat::Markdown
                    ? $this->markdown->toHtml($lesson->content)
                    : ($lesson->content ? '<p>'.e($lesson->content).'</p>' : null),
                'url' => route('courses.preview', [$course, $lesson]),
            ]);

        return view('courses.show', [
            'previews' => $previews,
            'course' => $course->load('modules.lessons'),
            'enrollment' => $existing && in_array($existing->status, ['active', 'completed'], true) ? $existing : null,
            'pendingCheckout' => $existing?->status === 'pending',
            'publishedReviews' => $course->reviews()->where('is_published', true)->with('enrollment.user')->latest()->get(),
            'instructor' => $identity !== [] ? array_merge($identity, ['bio' => $about['lead'] ?? null]) : null,
            'faq' => $faq,
            'jsonLd' => $this->courseJsonLd($course, $faq),
        ]);
    }

    /** @return array<string,mixed> */
    private function settingsJson(string $key): array
    {
        $raw = Settings::get($key);

        return $raw ? json_decode($raw, true) : [];
    }

    /**
     * §6.2 — Course + BreadcrumbList (+ FAQPage when real FAQ content exists) structured
     * data for the course detail page. Returned as a list of separate JSON-LD nodes, one
     * <script> tag per node — simpler and just as valid as merging into a single @graph.
     *
     * @return array<int,array<string,mixed>>
     */
    private function courseJsonLd(Course $course, array $faq): array
    {
        $nodes = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Course',
                'name' => $course->title,
                'description' => $course->cardTagline(),
                'provider' => [
                    '@type' => 'Person',
                    'name' => 'Muhindo Mubaraka',
                    'url' => route('home'),
                ],
                'hasCourseInstance' => array_filter([
                    '@type' => 'CourseInstance',
                    'courseMode' => 'online',
                    'courseWorkload' => $course->lessons_sum_duration_minutes
                        ? 'PT'.$course->lessons_sum_duration_minutes.'M' : null,
                ]),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $course->price,
                    'priceCurrency' => $course->currency,
                    'availability' => 'https://schema.org/InStock',
                    'url' => route('courses.show', $course),
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'e-Learning', 'item' => route('courses.index')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $course->title, 'item' => route('courses.show', $course)],
                ],
            ],
        ];

        if ($faq !== []) {
            $nodes[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $item) => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                ], $faq),
            ];
        }

        return $nodes;
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

        $invoice = Invoice::with('items')->findOrFail($enrollment->invoice_id);
        abort_unless($invoice->billable_type === User::class && $invoice->billable_id === $user->id, 403);

        // One payment screen for courses, source code and projects alike. This
        // route stays so existing links and bookmarks keep working.
        return redirect()->route('payments.show', $invoice);
    }
}
