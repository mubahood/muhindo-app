<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** Public read-only course catalogue for the mobile client. */
class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::where('is_published', true)->latest()->get();

        return ApiResponse::success($courses);
    }

    public function show(Course $course): JsonResponse
    {
        abort_unless($course->is_published, 404);

        return ApiResponse::success($course->load('modules.lessons'));
    }
}
