<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function create(Request $request, Course $course): View
    {
        $assignment = new Assignment(['lesson_id' => $request->integer('lesson_id') ?: null]);

        return view('admin.assignments.form', ['course' => $course, 'assignment' => $assignment]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $course->assignments()->create($this->validated($request));

        return redirect()->route('admin.courses.show', $course)->with('success', 'Assignment created.');
    }

    public function edit(Assignment $assignment): View
    {
        return view('admin.assignments.form', ['course' => $assignment->course, 'assignment' => $assignment]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $assignment->update($this->validated($request));

        return redirect()->route('admin.courses.show', $assignment->course)->with('success', 'Assignment updated.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $course = $assignment->course;
        $assignment->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Assignment deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'instructions' => 'nullable|string',
            'lesson_id' => 'nullable|exists:lessons,id',
            'due_at' => 'nullable|date',
            'points' => 'required|integer|min:1',
            'allow_late' => 'nullable|boolean',
            'late_penalty_percent' => 'nullable|integer|min:0|max:100',
            'max_file_mb' => 'required|integer|min:1|max:100',
            'allowed_types' => 'required|array|min:1',
            'allowed_types.*' => 'string|in:text,link,pdf,doc,docx,zip,jpg,png',
            'resubmit_until_graded' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ]);

        $data['allowed_types'] = implode(',', $data['allowed_types']);

        foreach (['allow_late', 'resubmit_until_graded', 'is_published'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $data;
    }
}
