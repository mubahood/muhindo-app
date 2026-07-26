<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CompletionRule;
use App\Enums\ContentFormat;
use App\Http\Controllers\Controller;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Services\Learning\MarkdownRenderer;
use App\Services\YoutubeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly YoutubeService $youtube,
    ) {}

    /** §7.4 — the split-pane editor's live preview renders through the exact same pipeline students see, so it can never drift from the real output. */
    public function previewMarkdown(Request $request): JsonResponse
    {
        $data = $request->validate(['content' => 'nullable|string']);

        return response()->json(['html' => $this->markdown->toHtml($data['content'] ?? '')]);
    }

    /** §7.5 — optional duration auto-fetch; a plain {available:false} when no API key is configured or the lookup fails. */
    public function fetchVideoDuration(Request $request): JsonResponse
    {
        $data = $request->validate(['video_url' => 'required|url']);

        $videoId = Lesson::extractYoutubeId($data['video_url']);
        if (! $videoId) {
            return response()->json(['available' => false, 'reason' => 'not_youtube']);
        }

        $minutes = $this->youtube->fetchDurationMinutes($videoId);
        if ($minutes === null) {
            return response()->json(['available' => false, 'reason' => 'unavailable']);
        }

        return response()->json(['available' => true, 'minutes' => $minutes]);
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

    /** §7.5 — the curriculum tree's quick-add row: a title only, everything else editable afterward. */
    public function storeInline(Request $request, CourseModule $module): RedirectResponse
    {
        $data = $request->validate(['title' => 'required|string|max:200']);

        $module->lessons()->create([
            'title' => $data['title'],
            'sort_order' => $module->lessons()->count(),
            'is_published' => false,
            'content_format' => ContentFormat::Plain,
            'completion_rule' => CompletionRule::Manual,
            'completion_threshold' => 80,
        ]);

        return redirect()->route('admin.courses.show', $module->course)->with('success', 'Lesson added as a draft — publish it once it has content.');
    }

    /** §7.5 — a quick per-lesson publish toggle, no full edit-form visit needed. */
    public function togglePublish(Lesson $lesson): RedirectResponse
    {
        $lesson->update(['is_published' => ! $lesson->is_published]);

        return redirect()->route('admin.courses.show', $lesson->module->course)
            ->with('success', $lesson->is_published ? 'Lesson published.' : 'Lesson unpublished.');
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
            'is_published' => 'nullable|boolean',
            'is_free_preview' => 'nullable|boolean',
            'content_format' => ['nullable', Rule::in(array_column(ContentFormat::cases(), 'value'))],
            'completion_rule' => ['nullable', Rule::in($enforcedRules)],
            'completion_threshold' => 'nullable|integer|min:1|max:100',
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['is_free_preview'] = $request->boolean('is_free_preview');
        $data['content_format'] = $data['content_format'] ?? ContentFormat::Plain->value;
        $data['completion_rule'] = $data['completion_rule'] ?? CompletionRule::Manual->value;
        $data['completion_threshold'] = $data['completion_threshold'] ?? 80;

        return $data;
    }
}
