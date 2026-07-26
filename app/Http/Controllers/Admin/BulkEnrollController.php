<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeCredentials;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** §7.5 — bulk enroll: paste emails, one per line or comma-separated; unknown emails get a real account. */
class BulkEnrollController extends Controller
{
    public function create(Course $course): View
    {
        return view('admin.courses.bulk-enroll', ['course' => $course]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $request->validate(['emails' => 'required|string']);

        $candidates = collect(preg_split('/[\s,]+/', trim($request->string('emails')->value())))
            ->map(fn ($e) => trim(strtolower($e)))
            ->filter()
            ->unique()
            ->values();

        $invalid = $candidates->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false);
        $valid = $candidates->diff($invalid);

        $created = 0;
        $enrolled = 0;
        $alreadyEnrolled = 0;

        foreach ($valid as $email) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $plainPassword = Str::password(16);
                $user = User::create([
                    'name' => Str::title(str_replace(['.', '_', '+'], ' ', explode('@', $email)[0])),
                    'email' => $email,
                    'role' => 'student',
                    'password' => Hash::make($plainPassword),
                    'password_change_required' => true,
                    'is_active' => true,
                ]);
                $created++;

                try {
                    Mail::to($user->email)->send(new WelcomeCredentials($user, $plainPassword));
                } catch (\Exception $e) {
                    Log::error("Welcome email failed for {$user->email}: ".$e->getMessage());
                }
            }

            $existingEnrollment = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
            if ($existingEnrollment) {
                $alreadyEnrolled++;

                continue;
            }

            Enrollment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => 'active',
                'source' => 'admin',
                'enrolled_at' => now(),
            ]);
            $enrolled++;
        }

        $summary = "{$enrolled} enrolled ({$created} new accounts created).";
        if ($alreadyEnrolled > 0) {
            $summary .= " {$alreadyEnrolled} were already enrolled.";
        }
        if ($invalid->isNotEmpty()) {
            $summary .= ' Skipped invalid: '.$invalid->implode(', ').'.';
        }

        return redirect()->route('admin.courses.show', $course)->with('success', $summary);
    }
}
