<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §7.3 — reviews: prompted once ≥50% progress, moderated (an admin must publish before it counts toward the average). */
class CourseReviewController extends Controller
{
    public function create(Request $request, Course $course): View
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($enrollment->progressPercent() >= 50, 403, 'Complete at least half the course before leaving a review.');

        return view('learn.review', ['course' => $course, 'review' => $enrollment->review]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        abort_unless($enrollment->progressPercent() >= 50, 403, 'Complete at least half the course before leaving a review.');

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'body' => 'nullable|string|max:3000',
        ]);

        $existing = $enrollment->review;
        $bodyChanged = $existing && ($existing->rating !== (int) $data['rating'] || $existing->body !== ($data['body'] ?? null));

        CourseReview::updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'course_id' => $course->id,
                'rating' => $data['rating'],
                'body' => $data['body'] ?? null,
                // A brand-new review, or an edit that actually changed content, needs (re-)moderation.
                'is_published' => $existing && ! $bodyChanged ? $existing->is_published : false,
            ],
        );

        return redirect()->route('learn.index')->with('success', 'Thanks for your review — it will appear once approved.');
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
