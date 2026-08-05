<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CourseCatalogueController;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view (shared by every role, owner, admin, student, client).
     */
    public function create(Request $request): View
    {
        return view('auth.login', [
            'intendedCourse' => $this->intendedCourse($request),
        ]);
    }

    /**
     * Handle an incoming authentication request. Every role signs in through
     * the same form; where they land afterwards is decided by DashboardController.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        if ($course = $this->intendedCourse($request)) {
            return app(CourseCatalogueController::class)->enroll($request, $course);
        }

        /* Same courtesy registration already extends: somebody who signed in
           with a basket waiting is sent to finish paying, not dropped on a
           dashboard to find their way back. An explicit intended URL still
           wins. It is the stronger signal about where they were headed. */
        $fallback = app(\App\Services\Shop\Cart::class)->isEmpty()
            ? \App\Support\AfterAuth::destination($user)
            : route('checkout.review');

        /* A client who has not proposed anything yet goes to do that even if
           they had a page in mind, they made this account to hire somebody,
           and the proposal is the step that makes that real. */
        if (\App\Support\AfterAuth::mustPropose($user)) {
            return redirect()->route('propose');
        }

        return redirect()->intended($fallback);
    }

    /** A guest arriving via "Enrol now" on a course page carries the course through sign-in. */
    private function intendedCourse(Request $request): ?Course
    {
        $slug = $request->string('intended_course')->trim()->value();

        return $slug !== '' ? Course::where('slug', $slug)->where('is_published', true)->first() : null;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
