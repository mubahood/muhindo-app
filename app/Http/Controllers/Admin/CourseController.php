<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseProgression;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            // The curriculum lists each lesson's own quizzes and tasks beneath
            // it, so they load with the lesson rather than one query per row.
            'course' => $course->load(
                'modules.lessons.materials',
                'modules.lessons.quizzes',
                'modules.lessons.assignments',
                'quizzes.lesson',
                'assignments.lesson',
                'announcements',
            ),
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
            'tagline' => 'nullable|string|max:160',
            'outcomes' => 'nullable|string',
            'requirements' => 'nullable|string',
            'cover_image' => 'nullable|string|max:255',
            'cover_alt' => 'nullable|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:5',
            'level' => 'required|in:beginner,intermediate,advanced',
            'category' => 'nullable|string|max:100',
            'is_published' => 'nullable|boolean',
            'progression' => ['nullable', Rule::in(array_column(CourseProgression::cases(), 'value'))],
            'access_duration_days' => 'nullable|integer|min:1',
            'debug_mode' => 'nullable|boolean',
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['title']);
        $data['currency'] = ($data['currency'] ?? null) ?: 'UGX';
        $data['is_published'] = $request->boolean('is_published');
        $data['debug_mode'] = $request->boolean('debug_mode');
        $data['progression'] = $data['progression'] ?? CourseProgression::Free->value;
        $data['outcomes'] = $this->linesToArray($data['outcomes'] ?? null);
        $data['requirements'] = $this->linesToArray($data['requirements'] ?? null);

        return $data;
    }

    /** One-per-line textarea input -> a clean array, or null (hides the section) when empty. */
    private function linesToArray(?string $raw): ?array
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $raw)), fn ($line) => $line !== ''));

        return $lines !== [] ? $lines : null;
    }
}
