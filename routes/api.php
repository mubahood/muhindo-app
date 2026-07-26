<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\ProjectController;
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
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        // Student: my enrollments/progress
        Route::get('my/enrollments', [EnrollmentController::class, 'mine'])->name('api.enrollments.mine');
        Route::post('courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('api.enrollments.store');
        Route::post('lessons/{lesson}/complete', [EnrollmentController::class, 'completeLesson'])->name('api.lessons.complete');

        // Client: my projects
        Route::get('my/projects', [ProjectController::class, 'mine'])->name('api.projects.mine');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('api.projects.show');

        // Invoices (scoped to the authenticated billable — client or student)
        Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);

        Route::post('device-tokens', [\App\Http\Controllers\Admin\DeviceTokenController::class, 'store'])->name('api.device-tokens.store');
    });
});
