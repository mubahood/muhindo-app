<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CourseCatalogueController;
use App\Models\Course;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * One public sign-up form for everyone. The visitor chooses what they're here
 * for — learning, hiring, or both — and the form pre-selects that from where
 * they came: a course page defaults to learning, the "Start a project" page to
 * hiring. Capabilities are independent flags, so choosing both is a first-class
 * option rather than two separate accounts.
 */
class StudentRegistrationController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function create(Request $request): View
    {
        return view('auth.register', [
            'intendedCourse' => $this->intendedCourse($request),
            'defaultAccountType' => $this->defaultAccountType($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Registration is the most valuable public form on the site to a
        // spammer, so it carries the same shield as the contact forms.
        if (\App\Support\Spam\FormShield::looksAutomated($request->all())) {
            return redirect()->route('register')->with('success', 'Check your inbox to continue.');
        }

        \App\Support\Spam\FormShield::assertHumanTiming($request->all());

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|string|email|max:150|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'account_type' => 'required|in:student,client,both',
            'terms' => 'accepted',
        ]);

        $isStudent = in_array($data['account_type'], ['student', 'both'], true);
        $isClient = in_array($data['account_type'], ['client', 'both'], true);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            // `role` stays the primary role for Spatie/permission purposes; a
            // "both" account is primarily a student who can also hire.
            'role' => $isStudent ? 'student' : 'client',
            'is_student' => $isStudent,
            'is_client' => $isClient,
            'is_active' => true,
        ]);

        // A client needs a client record to own projects and be invoiced against.
        if ($isClient) {
            $this->accounts->ensureClientProfile($user);
        }

        event(new Registered($user));

        // remember=true — same policy as login: signed in until they sign out.
        Auth::login($user, true);

        if ($isStudent && $course = $this->intendedCourse($request)) {
            return app(CourseCatalogueController::class)->enroll($request, $course);
        }

        /* A basket survives registration, so somebody who signed up at
           checkout is sent back to finish paying rather than dropped on a
           dashboard to find their way back. */
        if (! app(\App\Services\Shop\Cart::class)->isEmpty()) {
            return redirect()->route('checkout.review')
                ->with('success', 'Welcome! Your basket is still here — finish when you are ready.');
        }

        if (! $isStudent) {
            return redirect()->route('portal.index')
                ->with('success', "Welcome! Tell me about your project and I'll be in touch.");
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome! Your account has been created.');
    }

    /** §3.2 — a guest arriving via "Enrol now" on a course page carries the course through registration. */
    private function intendedCourse(Request $request): ?Course
    {
        $slug = $request->string('intended_course')->trim()->value();

        return $slug !== '' ? Course::where('slug', $slug)->where('is_published', true)->first() : null;
    }

    /**
     * Pre-select the account type from the journey that led here: an explicit
     * ?account_type, else a course context means learning, else the referring
     * page ("start a project" means hiring). Falls back to student.
     */
    private function defaultAccountType(Request $request): string
    {
        $explicit = $request->string('account_type')->trim()->value();
        if (in_array($explicit, ['student', 'client', 'both'], true)) {
            return $explicit;
        }

        if ($this->intendedCourse($request) !== null) {
            return 'student';
        }

        $referer = (string) $request->headers->get('referer');

        return $referer !== '' && str_contains($referer, 'start-a-project') ? 'client' : 'student';
    }
}
