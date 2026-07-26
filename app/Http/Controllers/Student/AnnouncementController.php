<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\MarkdownRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §7.3 — the student's Announcements tab: published announcements, newest first. */
class AnnouncementController extends Controller
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    public function index(Request $request, Course $course): View
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        $announcements = $course->announcements()->whereNotNull('published_at')->latest('published_at')->get();
        $rendered = $announcements->mapWithKeys(fn ($a) => [$a->id => $this->markdown->toHtml($a->body)]);

        return view('learn.announcements.index', ['course' => $course, 'announcements' => $announcements, 'rendered' => $rendered]);
    }
}
