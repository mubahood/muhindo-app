<?php

namespace App\Http\Controllers\Student;

use App\Enums\ContentFormat;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\PracticeWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PracticeWorkspaceController extends Controller
{
    public function show(Request $request, Course $course): View
    {
        $this->authorizeCourse($request, $course);
        $teachingMode = $request->boolean('teach');
        abort_unless(! $teachingMode || $request->user()->isAdmin(), 403);

        PracticeWorkspace::where('user_id', $request->user()->id)
            ->where('expires_at', '<=', now())->delete();
        $workspaces = PracticeWorkspace::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())->latest('updated_at')->get();
        $sourceLesson = null;

        if ($request->filled('lesson')) {
            $sourceLesson = Lesson::whereKey($request->integer('lesson'))
                ->whereHas('module', fn ($query) => $query->where('course_id', $course->id))
                ->where('is_published', true)->firstOrFail();
            $workspace = $this->importLesson($sourceLesson, $request->user()->id);
        } elseif ($request->filled('workspace')) {
            $workspace = $workspaces->firstWhere('id', $request->integer('workspace'));
            abort_unless($workspace, 404);
        } else {
            $workspace = $workspaces->first() ?? $this->createFreshWorkspace($request->user()->id);
        }

        $workspaces = PracticeWorkspace::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())->latest('updated_at')->get();

        $teachingProgressTotal = 0;
        $teachingProgressCount = 0;
        if ($teachingMode) {
            $publishedLessonIds = Lesson::whereHas('module', fn ($query) => $query->where('course_id', $course->id))
                ->where('is_published', true)->pluck('id');
            $teachingProgressTotal = $publishedLessonIds->count();
            $teachingProgressCount = DB::table('lesson_teaching_progress')
                ->where('user_id', $request->user()->id)
                ->whereNotNull('taught_at')->whereIn('lesson_id', $publishedLessonIds)
                ->distinct('lesson_id')->count('lesson_id');
        }
        $teachingProgressPercent = $teachingProgressTotal > 0
            ? (int) round($teachingProgressCount * 100 / $teachingProgressTotal)
            : 0;

        return view('learn.playground.show', compact(
            'course', 'workspace', 'workspaces', 'sourceLesson', 'teachingMode',
            'teachingProgressTotal', 'teachingProgressCount', 'teachingProgressPercent'
        ));
    }

    public function save(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
            'html_content' => ['required', 'string', 'max:32768'],
            'css_content' => ['required', 'string', 'max:32768'],
            'js_content' => ['required', 'string', 'max:32768'],
            'bootstrap_enabled' => ['required', 'boolean'],
        ]);
        abort_if(strlen($data['html_content'].$data['css_content'].$data['js_content']) > 98304, 413, 'Keep the total practice code under 96 KB.');

        $workspace = PracticeWorkspace::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())->findOrFail($data['workspace_id']);
        $workspace->fill(collect($data)->except('workspace_id')->all() + ['expires_at' => now()->addHours(12)])->save();

        return response()->json([
            'saved' => true,
            'expires_at' => $workspace->expires_at->toIso8601String(),
            'message' => 'Saved for 12 hours.',
        ]);
    }

    public function clear(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($request, $course);
        $workspace = $this->createFreshWorkspace($request->user()->id);

        return redirect()->route('learn.playground', [$course, 'workspace' => $workspace->id, 'teach' => $request->boolean('teach') ?: null])
            ->with('success', 'A fresh workspace is ready. Your earlier practice is still available until it expires.');
    }

    public function openSnippet(Request $request, Course $course): JsonResponse
    {
        $this->authorizeCourse($request, $course);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'language' => ['required', 'in:html,css,javascript'],
            'code' => ['required', 'string', 'max:32768'],
            'example_html' => ['nullable', 'string', 'max:32768'],
            'teaching_mode' => ['nullable', 'boolean'],
        ]);
        abort_unless(! ($data['teaching_mode'] ?? false) || $request->user()->isAdmin(), 403);
        abort_if(strlen($data['code'].($data['example_html'] ?? '')) > 65536, 413, 'This snippet is too large to open in practice.');

        $lesson = Lesson::whereKey($data['lesson_id'])
            ->whereHas('module', fn ($query) => $query->where('course_id', $course->id))
            ->where('is_published', true)->firstOrFail();

        $html = $data['language'] === 'html' ? $data['code'] : ($data['example_html'] ?: '<main><h1>Practice example</h1><p>Change the code, then run it again.</p></main>');
        $workspace = $this->createWorkspace(
            $request->user()->id,
            'Snippet: '.$lesson->title.' ('.strtoupper($data['language'] === 'javascript' ? 'JS' : $data['language']).')',
            $html,
            $data['language'] === 'css' ? $data['code'] : '',
            $data['language'] === 'javascript' ? $data['code'] : '',
        );

        $url = route('learn.playground', [
            $course,
            'workspace' => $workspace->id,
            'teach' => ($data['teaching_mode'] ?? false) ? 1 : null,
        ]);

        return response()->json(['success' => true, 'url' => $url]);
    }

    private function importLesson(Lesson $lesson, int $userId): PracticeWorkspace
    {
        $html = (string) $lesson->content;
        $css = '';
        if ($lesson->content_format === ContentFormat::Html && class_exists(\DOMDocument::class)) {
            $dom = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $html = '';
                foreach ($body->childNodes as $node) {
                    $html .= $dom->saveHTML($node);
                }
            }
            foreach ($dom->getElementsByTagName('style') as $style) {
                $css .= $style->textContent."\n";
            }
            $practiceStyles = public_path('bootstrap-practice-ground/css/practice.css');
            if (is_file($practiceStyles)) {
                $css .= file_get_contents($practiceStyles)."\n";
            }
        }

        // A lesson import replaces the current scratch work only after a teacher or
        // enrolled student explicitly chooses “Open in practice”.
        return $this->createWorkspace(
            $userId,
            'Practice: '.$lesson->title,
            $html,
            $css,
            '',
        );
    }

    private function createFreshWorkspace(int $userId): PracticeWorkspace
    {
        return $this->createWorkspace(
            $userId,
            'My practice',
            "<main>\n  <h1>Hello, Bootstrap!</h1>\n  <p>Change this page, then choose Run to see your result.</p>\n  <button class=\"btn btn-primary\">A Bootstrap button</button>\n</main>",
            "body {\n  font-family: system-ui, sans-serif;\n  padding: 1.5rem;\n}\n",
            "document.querySelector('button')?.addEventListener('click', () => {\n  console.log('The button works!');\n});",
        );
    }

    private function createWorkspace(int $userId, string $title, string $html, string $css, string $js): PracticeWorkspace
    {
        $workspace = PracticeWorkspace::create([
            'user_id' => $userId,
            'title' => Str::limit($title, 120, ''),
            'html_content' => $html,
            'css_content' => $css,
            'js_content' => $js,
            'bootstrap_enabled' => true,
            'expires_at' => now()->addHours(12),
        ]);

        $olderIds = PracticeWorkspace::where('user_id', $userId)->latest('updated_at')
            ->get(['id'])->slice(20)->pluck('id');
        if ($olderIds->isNotEmpty()) {
            PracticeWorkspace::whereIn('id', $olderIds)->delete();
        }

        return $workspace;
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)->firstOrFail();
        $this->authorize('access', $enrollment);
    }
}
