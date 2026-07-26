<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Support\VerificationCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The student's "My Courses" learning portal — enrolled courses, lesson player, certificates. */
class LearningController extends Controller
{
    public function index(Request $request): View
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)
            ->with('course')->latest()->get();

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

        $enrollment->progressRecords()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        if ($enrollment->progressPercent() >= 100 && $enrollment->status !== 'completed') {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
            $this->issueCertificate($enrollment);
        }

        $next = $this->nextLesson($course, $lesson);
        if ($next) {
            return redirect()->route('learn.lesson', [$course, $next])->with('success', 'Lesson completed!');
        }

        return redirect()->route('learn.index')->with('success', 'Course completed — congratulations!');
    }

    public function certificate(Certificate $certificate): \Symfony\Component\HttpFoundation\Response
    {
        $certificate->load('enrollment.user', 'enrollment.course');
        abort_unless($certificate->enrollment->user_id === request()->user()->id || request()->user()->isAdmin(), 403);

        return Pdf::loadView('pdf.certificate', ['certificate' => $certificate])
            ->stream("certificate-{$certificate->certificate_no}.pdf");
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

    private function issueCertificate(Enrollment $enrollment): Certificate
    {
        return Certificate::create([
            'enrollment_id' => $enrollment->id,
            'certificate_no' => VerificationCode::make('CRT', $enrollment->id),
            'issued_at' => now(),
        ]);
    }
}
