<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\GradebookService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The student's "Grades" tab: every quiz/assignment's counted grade + current course grade. */
class GradesController extends Controller
{
    public function __construct(private readonly GradebookService $gradebook) {}

    public function show(Request $request, Course $course): View
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return view('learn.grades', [
            'course' => $course,
            'items' => $this->gradebook->itemsFor($enrollment),
            'courseGrade' => $this->gradebook->courseGradePercent($enrollment),
        ]);
    }
}
