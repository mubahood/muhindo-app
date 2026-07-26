<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseModuleController extends Controller
{
    public function create(Course $course): View
    {
        return view('admin.courses.module-form', ['course' => $course, 'module' => new CourseModule]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'sort_order' => 'nullable|integer',
        ]);

        $course->modules()->create($data + ['sort_order' => $data['sort_order'] ?? $course->modules()->count()]);

        return redirect()->route('admin.courses.show', $course)->with('success', 'Module added.');
    }

    public function edit(CourseModule $module): View
    {
        return view('admin.courses.module-form', ['course' => $module->course, 'module' => $module]);
    }

    public function update(Request $request, CourseModule $module): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'sort_order' => 'nullable|integer',
        ]);

        $module->update($data);

        return redirect()->route('admin.courses.show', $module->course)->with('success', 'Module updated.');
    }

    public function destroy(CourseModule $module): RedirectResponse
    {
        $course = $module->course;
        $module->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Module deleted.');
    }
}
