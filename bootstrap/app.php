<?php

use App\Enums\ApiErrorCode;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Rate limiting on every API route. Laravel's slimmed-down skeleton
        // leaves the `api` group unthrottled unless asked; the 'api' limiter
        // itself is defined in AppServiceProvider.
        $middleware->throttleApi();

        // Flutterwave webhook is server-to-server (no session/CSRF token);
        // authenticity is verified via the verif-hash signature in the controller.
        $middleware->validateCsrfTokens(except: [
            'gateway/flutterwave/webhook',
            // sendBeacon fires while the page is being torn down and cannot
            // attach a session token. Authenticity comes from the encrypted
            // page handle in the payload instead.
            '_a',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\RequirePasswordChange::class,
            // Last in the group, and terminable: the recording happens after
            // the response has been flushed to the browser.
            \App\Http\Middleware\TrackVisitor::class,
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\IsAdmin::class,
            'super' => \App\Http\Middleware\IsSuperAdmin::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One consistent JSON envelope for every API error, never Laravel's
        // default ad hoc shape per exception type.
        $wantsJson = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return ApiResponse::error(
                ApiErrorCode::ValidationFailed,
                $e->getMessage(),
                $e->status,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return ApiResponse::error(ApiErrorCode::Unauthenticated, $e->getMessage(), 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return ApiResponse::error(ApiErrorCode::Forbidden, $e->getMessage(), 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            return ApiResponse::error(ApiErrorCode::NotFound, 'Resource not found.', 404);
        });

        // Catches everything else with an HTTP status (404 route-not-found,
        // 429 throttled, abort(403, '...'), ...).
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $code = match (true) {
                $status === 403 => ApiErrorCode::Forbidden,
                $status === 404 => ApiErrorCode::NotFound,
                $status === 429 => ApiErrorCode::TooManyRequests,
                $status >= 500 => ApiErrorCode::ServerError,
                default => ApiErrorCode::ServerError,
            };

            return ApiResponse::error(
                $code,
                $e->getMessage() ?: 'An error occurred.',
                $status,
            );
        });

        $exceptions->render(function (\Throwable $e, Request $request) use ($wantsJson) {
            if (! $wantsJson($request) || $e instanceof HttpExceptionInterface) {
                return null;
            }

            $message = config('app.debug') ? $e->getMessage() : 'Something went wrong.';

            return ApiResponse::error(ApiErrorCode::ServerError, $message, 500);
        });
    })->create();
