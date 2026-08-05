<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentFormat;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\MarkdownRenderer;
use App\Services\Learning\ProgressService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** P5.4, lesson detail for the mobile player: content, video source, resume position, lock state. */
class LessonController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
        $this->authorize('access', $enrollment);
        $this->authorize('view', [$lesson, $enrollment]);

        $this->progress->recordView($enrollment, $lesson);

        $renderedContent = $lesson->content && $lesson->content_format === ContentFormat::Markdown
            ? $this->markdown->toHtml($lesson->content)
            : null;

        $progress = $enrollment->progressRecords()->where('lesson_id', $lesson->id)->first();
        $resumePositionSeconds = $progress ? ($progress->last_position_seconds ?? 0) : 0;

        return ApiResponse::success([
            'lesson' => $lesson->load('materials', 'quizzes', 'assignments'),
            'rendered_content' => $renderedContent,
            'video_stream_url' => $lesson->hasSelfHostedVideo()
                ? URL::temporarySignedRoute('api.lessons.video-stream', now()->addHours(6), ['course' => $course, 'lesson' => $lesson])
                : null,
            'resume_position_seconds' => $resumePositionSeconds,
            'completed' => $progress?->completed_at !== null,
        ]);
    }
}
