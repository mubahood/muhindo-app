<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\CertificateService;
use App\Services\Learning\ProgressService;
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
    ) {}

    public function index(Request $request): View
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)
            ->with(['course' => fn ($query) => $query->withCount('lessons'), 'certificate'])
            ->withCount(['progressRecords as completed_lessons_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->latest()->get();

        return view('learn.index', ['enrollments' => $enrollments]);
    }

    public function show(Request $request, Course $course): View|RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);

        $firstLesson = $course->modules->first()?->lessons->first();
        if ($firstLesson) {
            return redirect()->route('learn.lesson', [$course, $firstLesson]);
        }

        return view('learn.course', ['course' => $course, 'enrollment' => $enrollment]);
    }

    public function lesson(Request $request, Course $course, Lesson $lesson): View
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->progress->recordView($enrollment, $lesson);

        $completedLessonIds = $enrollment->progressRecords()->whereNotNull('completed_at')->pluck('lesson_id');

        return view('learn.lesson', [
            'course' => $course->load('modules.lessons'),
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'completedLessonIds' => $completedLessonIds,
        ]);
    }

    public function complete(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $this->progress->completeLesson($enrollment, $lesson);

        $next = $this->nextLesson($course, $lesson);
        if ($next) {
            return redirect()->route('learn.lesson', [$course, $next])->with('success', 'Lesson completed!');
        }

        return redirect()->route('learn.index')->with('success', 'Course completed — congratulations!');
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
        $flat = $course->modules->flatMap(fn ($m) => $m->lessons);
        $index = $flat->search(fn (Lesson $l) => $l->id === $current->id);

        return $index === false ? null : $flat->get($index + 1);
    }
}
