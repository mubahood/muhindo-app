<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\Learning\EnrollmentCreated;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\ProgressService;
use App\Support\ApiResponse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function mine(Request $request): JsonResponse
    {
        $enrollments = Enrollment::where('user_id', $request->user()->id)->with('course')->latest()->get();

        return ApiResponse::success($enrollments);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $existing = Enrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->first();
        if ($existing) {
            return ApiResponse::success($existing, 'Already enrolled.');
        }

        if (! $course->isFree()) {
            return ApiResponse::error(\App\Enums\ApiErrorCode::Forbidden, 'This course requires checkout.', 402);
        }

        try {
            $enrollment = Enrollment::firstOrCreate(
                ['user_id' => $request->user()->id, 'course_id' => $course->id],
                ['uuid' => (string) Str::uuid(), 'status' => 'active', 'source' => 'self', 'enrolled_at' => now(), 'expires_at' => $course->enrollmentExpiresAt()],
            );
            if ($enrollment->wasRecentlyCreated) {
                EnrollmentCreated::dispatch($enrollment);
            }
        } catch (UniqueConstraintViolationException) {
            // A concurrent request (double-tap) won the race — the enrollment exists either way, no new event.
            $enrollment = Enrollment::where('user_id', $request->user()->id)->where('course_id', $course->id)->firstOrFail();
        }

        return ApiResponse::success($enrollment, 'Enrolled.', 201);
    }

    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $lesson->module->course_id)
            ->firstOrFail();

        $progress = $this->progress->completeLesson($enrollment, $lesson);

        return ApiResponse::success($progress);
    }

    /** §6.2 — same ProgressService::recordHeartbeat() the web player calls; player-agnostic by design. */
    public function heartbeat(Request $request, Lesson $lesson): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $lesson->module->course_id)
            ->firstOrFail();

        $data = $request->validate([
            'seconds_delta' => 'required|integer|min:0|max:60',
            'position_seconds' => 'required|integer|min:0',
        ]);

        $progress = $this->progress->recordHeartbeat($enrollment, $lesson, $data['seconds_delta'], $data['position_seconds']);

        return ApiResponse::success([
            'watch_seconds' => $progress->watch_seconds,
            'last_position_seconds' => $progress->last_position_seconds,
            'completed' => $progress->completed_at !== null,
        ]);
    }
}
