<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnrollmentController extends Controller
{
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

        $enrollment = Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return ApiResponse::success($enrollment, 'Enrolled.', 201);
    }

    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $lesson->module->course_id)
            ->firstOrFail();

        $progress = $enrollment->progressRecords()->updateOrCreate(
            ['lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        return ApiResponse::success($progress);
    }
}
