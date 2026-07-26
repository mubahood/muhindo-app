<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function create(CourseModule $module): View
    {
        return view('admin.courses.lesson-form', ['module' => $module, 'lesson' => new Lesson]);
    }

    public function store(Request $request, CourseModule $module): RedirectResponse
    {
        $data = $this->validated($request);
        $module->lessons()->create($data + ['sort_order' => $data['sort_order'] ?? $module->lessons()->count()]);

        return redirect()->route('admin.courses.show', $module->course)->with('success', 'Lesson added.');
    }

    public function edit(Lesson $lesson): View
    {
        return view('admin.courses.lesson-form', ['module' => $lesson->module, 'lesson' => $lesson]);
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $lesson->update($this->validated($request));

        return redirect()->route('admin.courses.show', $lesson->module->course)->with('success', 'Lesson updated.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $course = $lesson->module->course;
        $lesson->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Lesson deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'video_url' => 'nullable|url|max:500',
            'duration_minutes' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer',
            'is_free_preview' => 'nullable|boolean',
        ]);

        $data['is_free_preview'] = $request->boolean('is_free_preview');

        return $data;
    }
}
