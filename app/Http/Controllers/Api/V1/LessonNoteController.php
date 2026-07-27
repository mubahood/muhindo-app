<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** P5.4 — the API equivalent of Student\LessonNoteController, mirroring the same enrollment/lesson ownership checks. */
class LessonNoteController extends Controller
{
    public function index(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($lesson->module->course_id === $course->id, 404);

        $notes = $enrollment->lessonNotes()->where('lesson_id', $lesson->id)->orderBy('seconds')->get();

        return ApiResponse::success($notes);
    }

    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
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

        return ApiResponse::success($note, 'Note saved.', 201);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, LessonNote $note): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($note->enrollment_id === $enrollment->id, 404);
        abort_unless($note->lesson_id === $lesson->id, 404);

        $note->delete();

        return ApiResponse::success(null, 'Note deleted.');
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
