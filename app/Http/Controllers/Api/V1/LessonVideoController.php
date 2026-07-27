<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P5.4/P5.3 — the mobile equivalent of Student\LessonVideoController::stream(). Deliberately
 * `signed`-only, with no `auth:sanctum` layer on top: a native mobile video player generally
 * can't attach a bearer token to the request its own OS media stack makes, so — unlike the web
 * version, where the browser sends the session cookie for free — the signature has to be the
 * sole credential here. Authorization is still fully enforced, just earlier: at mint time, in
 * Api\V1\LessonController::show(), which already runs the same EnrollmentPolicy/LessonPolicy
 * checks before it ever hands out a signed URL. The 6-hour window bounds how long a leaked link
 * keeps working.
 */
class LessonVideoController extends Controller
{
    public function stream(Course $course, Lesson $lesson): BinaryFileResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($lesson->hasSelfHostedVideo(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($lesson->video_disk_path), 404);

        return response()->file($disk->path($lesson->video_disk_path), [
            'Content-Type' => $disk->mimeType($lesson->video_disk_path) ?: 'video/mp4',
        ]);
    }
}
