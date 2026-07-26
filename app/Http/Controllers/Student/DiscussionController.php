<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\DiscussionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §7.3 — the student's Q&A tab: course-wide + per-lesson threads. */
class DiscussionController extends Controller
{
    public function __construct(private readonly DiscussionService $discussions) {}

    public function index(Request $request, Course $course): View
    {
        $this->enrollmentFor($request, $course);

        $threads = $course->discussions()
            ->whereNull('parent_id')
            ->withCount('replies')
            ->with('user', 'lesson')
            ->latest()
            ->get();

        return view('learn.discussions.index', ['course' => $course, 'threads' => $threads]);
    }

    public function create(Request $request, Course $course): View
    {
        $this->enrollmentFor($request, $course);

        $lesson = $request->filled('lesson_id') ? Lesson::find($request->integer('lesson_id')) : null;

        return view('learn.discussions.create', ['course' => $course, 'lesson' => $lesson]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->enrollmentFor($request, $course);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'lesson_id' => 'nullable|exists:lessons,id',
        ]);

        $lesson = isset($data['lesson_id']) ? Lesson::find($data['lesson_id']) : null;
        $thread = $this->discussions->ask($request->user(), $course, $lesson, $data['body']);

        return redirect()->route('learn.discussions.show', [$course, $thread])->with('success', 'Question posted.');
    }

    public function show(Request $request, Course $course, Discussion $discussion): View
    {
        $this->enrollmentFor($request, $course);
        $this->guardDiscussion($discussion, $course);

        return view('learn.discussions.show', [
            'course' => $course,
            'discussion' => $discussion->load('user', 'lesson', 'replies.user'),
        ]);
    }

    public function reply(Request $request, Course $course, Discussion $discussion): RedirectResponse
    {
        $this->enrollmentFor($request, $course);
        $this->guardDiscussion($discussion, $course);

        $data = $request->validate(['body' => 'required|string|max:5000']);
        $this->discussions->reply($request->user(), $discussion, $data['body']);

        return redirect()->route('learn.discussions.show', [$course, $discussion])->with('success', 'Reply posted.');
    }

    public function resolve(Request $request, Course $course, Discussion $discussion): RedirectResponse
    {
        $this->enrollmentFor($request, $course);
        $this->guardDiscussion($discussion, $course);
        abort_unless($discussion->user_id === $request->user()->id || $request->user()->isAdmin(), 403);

        $this->discussions->resolve($discussion);

        return redirect()->route('learn.discussions.show', [$course, $discussion])->with('success', 'Marked resolved.');
    }

    private function enrollmentFor(Request $request, Course $course): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return $enrollment;
    }

    private function guardDiscussion(Discussion $discussion, Course $course): void
    {
        abort_unless($discussion->course_id === $course->id, 404);
    }
}
