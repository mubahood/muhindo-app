<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a self-hosted lesson video from the private disk. The route itself is
 * `signed` (tamper-proof, time-limited, and doesn't leak the real storage path), but the
 * signature alone isn't trusted as authorization: this still re-checks the same
 * EnrollmentPolicy/LessonPolicy every other lesson surface enforces, so a leaked link is
 * bounded by both the signature's expiry *and* the requester actually being the enrolled,
 * logged-in student. `response()->file()` gets Range-request (seek) support for free via
 * Symfony's BinaryFileResponse::prepare().
 */
class LessonVideoController extends Controller
{
    public function stream(Request $request, Course $course, Lesson $lesson): BinaryFileResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($lesson->hasSelfHostedVideo(), 404);

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
        $this->authorize('access', $enrollment);
        $this->authorize('view', [$lesson, $enrollment]);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($lesson->video_disk_path), 404);

        return response()->file($disk->path($lesson->video_disk_path), [
            'Content-Type' => $disk->mimeType($lesson->video_disk_path) ?: 'video/mp4',
        ]);
    }
}
