<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseCatalogueController extends Controller
{
    public function index(): View
    {
        return view('courses.index', [
            'courses' => Course::where('is_published', true)->latest()->get(),
        ]);
    }

    public function show(Course $course): View
    {
        abort_unless($course->is_published || auth()->user()?->isAdmin(), 404);

        $enrollment = auth()->check()
            ? Enrollment::where('user_id', auth()->id())->where('course_id', $course->id)->first()
            : null;

        return view('courses.show', [
            'course' => $course->load('modules.lessons'),
            'enrollment' => $enrollment,
        ]);
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        $user = $request->user();

        $existing = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->first();
        if ($existing) {
            return redirect()->route('learn.course', $course)->with('success', 'You are already enrolled in this course.');
        }

        if (! $course->isFree()) {
            // Paid courses go through checkout (Flutterwave) before enrollment is created there.
            return redirect()->route('courses.show', $course)
                ->with('error', 'Paid checkout is coming soon — contact me directly to enrol in this course for now.');
        }

        Enrollment::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
            'source' => 'self',
            'enrolled_at' => now(),
        ]);

        return redirect()->route('learn.course', $course)->with('success', 'You are enrolled — happy learning!');
    }
}
