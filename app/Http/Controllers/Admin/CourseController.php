<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(): View
    {
        return view('admin.courses.index', [
            'courses' => Course::withCount('enrollments')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.courses.form', ['course' => new Course]);
    }

    public function store(Request $request): RedirectResponse
    {
        $course = Course::create($this->validated($request) + [
            'uuid' => (string) Str::uuid(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.courses.show', $course)->with('success', 'Course created.');
    }

    public function show(Course $course): View
    {
        return view('admin.courses.show', [
            'course' => $course->load('modules.lessons.materials'),
        ]);
    }

    public function edit(Course $course): View
    {
        return view('admin.courses.form', ['course' => $course]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($this->validated($request, $course));

        return redirect()->route('admin.courses.show', $course)->with('success', 'Course updated.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'slug' => 'nullable|string|max:200|alpha_dash|unique:courses,slug,'.($course !== null ? $course->id : 'NULL').',id',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:5',
            'level' => 'required|in:beginner,intermediate,advanced',
            'category' => 'nullable|string|max:100',
            'is_published' => 'nullable|boolean',
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        $data['currency'] = ($data['currency'] ?? null) ?: 'UGX';
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
