<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\GradebookService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** P5.4 — the API equivalent of Student\GradesController. */
class GradeController extends Controller
{
    public function __construct(private readonly GradebookService $gradebook) {}

    public function show(Request $request, Course $course): JsonResponse
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return ApiResponse::success([
            'items' => $this->gradebook->itemsFor($enrollment),
            'course_grade_percent' => $this->gradebook->courseGradePercent($enrollment),
        ]);
    }
}
