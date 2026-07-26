<?php

namespace App\Http\Controllers;

use App\Enums\ContentFormat;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\MarkdownRenderer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseCatalogueController extends Controller
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    public function index(): View
    {
        return view('courses.index', [
            'courses' => Course::where('is_published', true)->latest()->get(),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless($course->is_published || auth()->user()?->isAdmin(), 404);

        $enrollment = auth()->check()
            ? Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->first()
            : null;

        return view('courses.show', [
            'course' => $course->load('modules.lessons'),
            'enrollment' => $enrollment,
        ]);
    }

    /** §7.2 — free preview: a guest (no enrollment) can view an is_free_preview lesson, closing L5. */
    public function preview(Course $course, Lesson $lesson): View
    {
        abort_unless($course->is_published, 404);
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($lesson->is_free_preview, 404);

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
        if ($existing) {
            return redirect()->route('learn.course', $course)->with('success', 'You are already enrolled in this course.');
        }

        if (! $course->isFree()) {
            // Paid courses go through checkout (Flutterwave) before enrollment is created there.
            return redirect()->route('courses.show', $course)
                ->with('error', 'Paid checkout is coming soon — contact me directly to enrol in this course for now.');
        }

        try {
            Enrollment::firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['uuid' => (string) Str::uuid(), 'status' => 'active', 'source' => 'self', 'enrolled_at' => now()],
            );
        } catch (UniqueConstraintViolationException) {
            // A concurrent request (double-click) won the race — the enrollment exists either way.
        }

        return redirect()->route('learn.course', $course)->with('success', 'You are enrolled — happy learning!');
    }
}
