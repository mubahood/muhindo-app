<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CourseCatalogueController;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Public self-registration — students only. Clients are onboarded manually
 * by the owner (see admin.clients.store), never self-register.
 */
class StudentRegistrationController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'intendedCourse' => $this->intendedCourse($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|string|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => 'accepted',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($course = $this->intendedCourse($request)) {
            return app(CourseCatalogueController::class)->enroll($request, $course);
        }

        return redirect()->route('dashboard')->with('success', 'Welcome! Your account has been created.');
    }

    /** §3.2 — a guest arriving via "Enrol now" on a course page carries the course through registration. */
    private function intendedCourse(Request $request): ?Course
    {
        $slug = $request->string('intended_course')->trim()->value();

        return $slug !== '' ? Course::where('slug', $slug)->where('is_published', true)->first() : null;
    }
}
