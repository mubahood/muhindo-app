<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** §7.3 — private, timestamped student notes on a lesson; each is a discrete entry, not an editable document. */
class LessonNoteController extends Controller
{
    public function store(Request $request, Course $course, Lesson $lesson): RedirectResponse|JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'seconds' => 'nullable|integer|min:0',
        ]);

        $note = $enrollment->lessonNotes()->create([
            'lesson_id' => $lesson->id,
            'body' => $data['body'],
            'seconds' => $data['seconds'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'note' => ['id' => $note->id, 'body' => $note->body, 'seconds' => $note->seconds, 'formatted_time' => $note->formattedTime()],
            ]);
        }

        return back()->with('success', 'Note saved.');
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, LessonNote $note): RedirectResponse|JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($note->enrollment_id === $enrollment->id, 404);
        abort_unless($note->lesson_id === $lesson->id, 404);

        $note->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Note deleted.');
    }

    private function enrollmentFor(Request $request, Course $course): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return $enrollment;
    }
}
