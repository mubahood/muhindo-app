<?php

namespace App\Http\Controllers\Api\V1;

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
                ['uuid' => (string) Str::uuid(), 'status' => 'active', 'source' => 'self', 'enrolled_at' => now()],
            );
        } catch (UniqueConstraintViolationException) {
            // A concurrent request (double-tap) won the race — the enrollment exists either way.
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
}
