<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(): View
    {
        return view('admin.enrollments.index', [
            'enrollments' => Enrollment::with(['user', 'course'])->latest()->paginate(30),
            'students' => User::where('role', 'student')->orderBy('name')->get(),
            'courses' => Course::orderBy('title')->get(),
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $existing = Enrollment::where('user_id', $data['user_id'])->where('course_id', $course->id)->first();
        if ($existing) {
            return back()->with('error', 'That student is already enrolled in this course.');
        }

        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $data['user_id'],
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'admin',
            'enrolled_at' => now(),
            'expires_at' => $course->enrollmentExpiresAt(),
        ]);

        return back()->with('success', 'Student enrolled.');
    }

    public function destroy(Enrollment $enrollment): RedirectResponse
    {
        $enrollment->delete();

        return back()->with('success', 'Enrollment removed.');
    }
}
