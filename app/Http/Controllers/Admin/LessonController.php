<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\Learning\MarkdownRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(private readonly MarkdownRenderer $markdown) {}

    /** §7.4 — the split-pane editor's live preview renders through the exact same pipeline students see, so it can never drift from the real output. */
    public function previewMarkdown(Request $request): JsonResponse
    {
        $data = $request->validate(['content' => 'nullable|string']);

        return response()->json(['html' => $this->markdown->toHtml($data['content'] ?? '')]);
    }

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
        $enforcedRules = array_map(fn (CompletionRule $r) => $r->value, array_filter(CompletionRule::cases(), fn ($r) => $r->isEnforced()));

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'video_url' => 'nullable|url|max:500',
            'duration_minutes' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer',
            'is_free_preview' => 'nullable|boolean',
            'content_format' => ['nullable', Rule::in(array_column(ContentFormat::cases(), 'value'))],
            'completion_rule' => ['nullable', Rule::in($enforcedRules)],
            'completion_threshold' => 'nullable|integer|min:1|max:100',
        ]);

        $data['is_free_preview'] = $request->boolean('is_free_preview');
        $data['content_format'] = $data['content_format'] ?? ContentFormat::Plain->value;
        $data['completion_rule'] = $data['completion_rule'] ?? CompletionRule::Manual->value;
        $data['completion_threshold'] = $data['completion_threshold'] ?? 80;

        return $data;
    }
}
