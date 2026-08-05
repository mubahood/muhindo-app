<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\QuizAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** P5.4, the API equivalent of Student\QuizAttemptController::index()/show(). */
class QuizController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);

        $quizzes = $course->quizzes()->where('is_published', true)->with('lesson')->get()
            ->map(fn (Quiz $quiz) => [
                'quiz' => $quiz,
                'latest_attempt' => $quiz->attempts()->where('enrollment_id', $enrollment->id)->latest('attempt_no')->first(),
            ]);

        return ApiResponse::success($quizzes);
    }

    public function show(Request $request, Course $course, Quiz $quiz): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($quiz->course_id === $course->id, 404);
        abort_unless($quiz->is_published, 404);

        return ApiResponse::success([
            'quiz' => $quiz,
            'attempts_used' => $quiz->attempts()->where('enrollment_id', $enrollment->id)->count(),
            'in_progress' => $quiz->attempts()->where('enrollment_id', $enrollment->id)->where('status', QuizAttemptStatus::InProgress)->first(),
            'best_attempt' => $quiz->attempts()->where('enrollment_id', $enrollment->id)->where('status', QuizAttemptStatus::Graded)->orderByDesc('score_percent')->first(),
        ]);
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
