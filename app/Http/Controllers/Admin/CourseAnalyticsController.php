<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Learning\CourseAnalyticsService;
use Illuminate\View\View;

class CourseAnalyticsController extends Controller
{
    public function show(Course $course, CourseAnalyticsService $analytics): View
    {
        return view('admin.courses.analytics', [
            'course' => $course,
            'funnel' => $analytics->funnel($course),
            'dropOff' => $analytics->lessonDropOff($course),
            'watchTime' => $analytics->watchTimeHistogram($course),
            'quizzes' => $analytics->quizSummaries($course),
        ]);
    }
}
