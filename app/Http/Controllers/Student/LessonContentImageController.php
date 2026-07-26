<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves a markdown lesson's embedded images inline — same policy gate as materials/heartbeat. */
class LessonContentImageController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson, string $filename): StreamedResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
        $this->authorize('access', $enrollment);
        $this->authorize('view', [$lesson, $enrollment]);

        // basename() collapses any ../ path traversal attempt down to a bare filename.
        $path = "lesson-content-images/{$lesson->id}/".basename($filename);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
