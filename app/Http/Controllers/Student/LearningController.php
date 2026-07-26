<?php

namespace App\Http\Controllers\Student;

use App\Enums\ContentFormat;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\CertificateService;
use App\Services\Learning\MarkdownRenderer;
use App\Services\Learning\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The student's "My Courses" learning portal — enrolled courses, lesson player, certificates. */
class LearningController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificates,
        private readonly ProgressService $progress,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function index(Request $request): View
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)
            ->with(['course' => fn ($query) => $query->withCount('lessons')
                ->withCount(['quizzes as published_quizzes_count' => fn ($q) => $q->where('is_published', true)])
                ->withCount(['assignments as published_assignments_count' => fn ($q) => $q->where('is_published', true)]),
                'certificate', 'lastLesson', 'review'])
            ->withCount(['progressRecords as completed_lessons_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->latest()->get();

        return view('learn.index', ['enrollments' => $enrollments]);
    }

    public function show(Request $request, Course $course): View|RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);

        // §6.5 resume UX: pick up where they left off rather than always
        // restarting at lesson #1. lastLesson() excludes soft-deleted lessons
        // via Lesson's own global scope, the course-id check guards against a
        // stale last_lesson_id left over from before a course was reworked, and
        // the lock check guards against the course having switched to
        // sequential progression since — redirecting straight into a 403 would
        // be a worse experience than just falling through to the first lesson.
        $resumeLesson = $enrollment->lastLesson;
        if ($resumeLesson && $resumeLesson->module->course_id === $course->id && $resumeLesson->is_published && ! $course->isLessonLocked($enrollment, $resumeLesson)) {
            return redirect()->route('learn.lesson', [$course, $resumeLesson]);
        }

        $firstLesson = $this->publishedLessonsFlat($course)->first();
        if ($firstLesson) {
            return redirect()->route('learn.lesson', [$course, $firstLesson]);
        }

        return view('learn.course', ['course' => $course, 'enrollment' => $enrollment]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): View
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($lesson->is_published, 404);

        $this->progress->recordView($enrollment, $lesson);

        $course->load('modules.lessons');
        $completedLessonIds = $enrollment->progressRecords()->whereNotNull('completed_at')->pluck('lesson_id');
        $lockedLessonIds = $this->publishedLessonsFlat($course)
            ->filter(fn (Lesson $l) => $course->isLessonLocked($enrollment, $l))
            ->pluck('id');

        $renderedContent = $lesson->content && $lesson->content_format === ContentFormat::Markdown
            ? $this->markdown->toHtml($lesson->content)
            : null;

        $notes = $enrollment->lessonNotes()->where('lesson_id', $lesson->id)->orderBy('seconds')->get();

        return view('learn.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'completedLessonIds' => $completedLessonIds,
            'lockedLessonIds' => $lockedLessonIds,
            'previousLesson' => $this->adjacentLesson($course, $lesson, -1),
            'nextLessonForNav' => $this->adjacentLesson($course, $lesson, 1),
            'renderedContent' => $renderedContent,
            'notes' => $notes,
        ]);
    }

    /**
     * §7.3 — "complete without reload": the same action serves both. A plain
     * form POST (no JS) gets the classic redirect; an AJAX POST (Accept:
     * application/json) gets a JSON summary the player uses for the
     * optimistic-UI auto-advance card and certificate modal. Every mutation
     * here is idempotent server-side already (ProgressService), so a
     * double-submit from either path is safe.
     */
    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->progress->completeLesson($enrollment, $lesson);
        $enrollment->refresh();

        $next = $this->nextLesson($course, $lesson);

        if ($request->wantsJson()) {
            $certificate = $enrollment->certificate;

            return response()->json([
                'success' => true,
                'progress_percent' => $enrollment->progress_percent,
                'course_completed' => $enrollment->status === 'completed',
                'next_lesson_url' => $next ? route('learn.lesson', [$course, $next]) : null,
                'next_lesson_title' => $next?->title,
                'certificate_url' => $certificate ? route('learn.certificate', $certificate) : null,
            ]);
        }

        if ($next) {
            return redirect()->route('learn.lesson', [$course, $next])->with('success', 'Lesson completed!');
        }

        return redirect()->route('learn.index')->with('success', 'Course completed — congratulations!');
    }

    /** §6.2/§7.3 — player heartbeat: every ~15s of actual playing time, reports watch progress. */
    public function heartbeat(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $data = $request->validate([
            'seconds_delta' => 'required|integer|min:0|max:60',
            'position_seconds' => 'required|integer|min:0',
        ]);

        $progress = $this->progress->recordHeartbeat($enrollment, $lesson, $data['seconds_delta'], $data['position_seconds']);

        return response()->json([
            'success' => true,
            'watch_seconds' => $progress->watch_seconds,
            'last_position_seconds' => $progress->last_position_seconds,
            'completed' => $progress->completed_at !== null,
        ]);
    }

    public function certificate(Certificate $certificate): StreamedResponse
    {
        $certificate->load('enrollment.user', 'enrollment.course');
        abort_unless($certificate->enrollment->user_id === request()->user()->id || request()->user()->isAdmin(), 403);

        return $this->certificates->stream($certificate);
    }

    private function enrollmentFor(Request $request, Course $course): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return $enrollment;
    }

    private function nextLesson(Course $course, Lesson $current): ?Lesson
    {
        return $this->adjacentLesson($course, $current, 1);
    }

    /** Powers both the "complete → auto-advance" flow and the ↑/↓ keyboard shortcuts. */
    private function adjacentLesson(Course $course, Lesson $current, int $offset): ?Lesson
    {
        $flat = $this->publishedLessonsFlat($course);
        $index = $flat->search(fn (Lesson $l) => $l->id === $current->id);

        return $index === false ? null : $flat->get($index + $offset);
    }

    /** §7.5 — a draft (unpublished) lesson is invisible to students: not counted, not navigable to, not resumable. */
    private function publishedLessonsFlat(Course $course): \Illuminate\Support\Collection
    {
        $course->loadMissing('modules.lessons');

        return $course->modules->flatMap(fn ($m) => $m->lessons)->where('is_published', true)->values();
    }
}
