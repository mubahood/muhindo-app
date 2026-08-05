<?php

use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\LessonNoteController;
use App\Http\Controllers\Api\V1\LessonVideoController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\QuizAttemptController;
use App\Http\Controllers\Api\V1\QuizController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| muhindo-app API v1
|--------------------------------------------------------------------------
| Sanctum-authenticated JSON API for the mobile client (future) and any
| headless consumer. Every response uses the App\Support\ApiResponse
| envelope; RBAC is enforced by the same Policies as the web app.
*/
Route::prefix('v1')->group(function () {
    // Public: docs + auth + course catalogue
    Route::get('openapi.json', OpenApiController::class)->name('api.openapi');
    Route::post('auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::apiResource('courses', CourseController::class)->names([
        'index' => 'api.courses.index',
        'show' => 'api.courses.show',
    ])->only(['index', 'show']);

    // Signed, time-limited, deliberately outside auth:sanctum (see LessonVideoController's
    // docblock: a native mobile video player generally can't attach a bearer token).
    Route::get('courses/{course}/lessons/{lesson}/video-stream', [LessonVideoController::class, 'stream'])
        ->middleware('signed')->name('api.lessons.video-stream');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // Student: my enrollments/progress
        Route::get('my/enrollments', [EnrollmentController::class, 'mine'])->name('api.enrollments.mine');
        Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])->middleware('throttle:10,1')->name('api.enrollments.store');
        Route::post('lessons/{lesson}/complete', [EnrollmentController::class, 'completeLesson'])->name('api.lessons.complete');
        Route::post('lessons/{lesson}/heartbeat', [EnrollmentController::class, 'heartbeat'])->middleware('throttle:20,1')->name('api.lessons.heartbeat');

        // Lesson detail + notes
        Route::get('courses/{course}/lessons/{lesson}', [LessonController::class, 'show'])->name('api.lessons.show');
        Route::get('courses/{course}/lessons/{lesson}/notes', [LessonNoteController::class, 'index'])->name('api.lesson-notes.index');
        Route::post('courses/{course}/lessons/{lesson}/notes', [LessonNoteController::class, 'store'])->name('api.lesson-notes.store');
        Route::delete('courses/{course}/lessons/{lesson}/notes/{note}', [LessonNoteController::class, 'destroy'])->name('api.lesson-notes.destroy');

        // Quizzes
        Route::get('courses/{course}/quizzes', [QuizController::class, 'index'])->name('api.quizzes.index');
        Route::get('courses/{course}/quizzes/{quiz}', [QuizController::class, 'show'])->name('api.quizzes.show');
        Route::post('courses/{course}/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])->name('api.quiz-attempts.start');
        Route::get('courses/{course}/quizzes/{quiz}/attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('api.quiz-attempts.show');
        Route::post('courses/{course}/quizzes/{quiz}/attempts/{attempt}/questions/{question}/answer', [QuizAttemptController::class, 'answer'])
            ->middleware('throttle:60,1')->name('api.quiz-attempts.answer');
        Route::post('courses/{course}/quizzes/{quiz}/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])->name('api.quiz-attempts.submit');
        Route::get('courses/{course}/quizzes/{quiz}/attempts/{attempt}/review', [QuizAttemptController::class, 'review'])->name('api.quiz-attempts.review');

        // Assignments
        Route::get('courses/{course}/assignments', [AssignmentController::class, 'index'])->name('api.assignments.index');
        Route::get('courses/{course}/assignments/{assignment}', [AssignmentController::class, 'show'])->name('api.assignments.show');
        Route::post('courses/{course}/assignments/{assignment}/draft', [AssignmentController::class, 'saveDraft'])->name('api.assignments.draft');
        Route::post('courses/{course}/assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->name('api.assignments.submit');
        Route::get('courses/{course}/assignments/{assignment}/submissions/{submission}/download', [AssignmentController::class, 'download'])->name('api.assignments.download');

        // Grades
        Route::get('courses/{course}/grades', [GradeController::class, 'show'])->name('api.grades.show');

        // Client: my projects
        Route::get('my/projects', [ProjectController::class, 'mine'])->name('api.projects.mine');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('api.projects.show');

        // Invoices (scoped to the authenticated billable, client or student)
        Route::apiResource('invoices', InvoiceController::class)->names([
            'index' => 'api.invoices.index',
            'show' => 'api.invoices.show',
        ])->only(['index', 'show']);

        Route::post('device-tokens', [\App\Http\Controllers\Admin\DeviceTokenController::class, 'store'])->name('api.device-tokens.store');
    });
});
