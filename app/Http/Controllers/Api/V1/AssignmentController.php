<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\Learning\AssignmentService;
use App\Services\Learning\MarkdownRenderer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** P5.4, the API equivalent of Student\AssignmentController's Classroom-style turn-in flow. */
class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly MarkdownRenderer $markdown,
    ) {}

    public function index(Request $request, Course $course): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);

        $assignments = $course->assignments()->where('is_published', true)->with('lesson')->get()
            ->map(fn (Assignment $assignment) => [
                'assignment' => $assignment,
                'latest' => $assignment->submissions()->where('enrollment_id', $enrollment->id)->latest('attempt_no')->first(),
            ]);

        return ApiResponse::success($assignments);
    }

    public function show(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAssignment($assignment, $course);
        abort_unless($assignment->is_published, 404);

        return ApiResponse::success([
            'assignment' => $assignment,
            'rendered_instructions' => $assignment->instructions ? $this->markdown->toHtml($assignment->instructions) : null,
            'latest' => $assignment->submissions()->where('enrollment_id', $enrollment->id)->latest('attempt_no')->first(),
            'history' => $assignment->submissions()->where('enrollment_id', $enrollment->id)
                ->where('status', '!=', 'draft')->oldest('attempt_no')->get(),
        ]);
    }

    public function saveDraft(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAssignment($assignment, $course);

        $data = $this->validated($request, $assignment);
        $submission = $this->assignments->saveDraft($enrollment, $assignment, $data, $request->file('file'));

        return ApiResponse::success($submission, 'Draft saved.');
    }

    public function submit(Request $request, Course $course, Assignment $assignment): JsonResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAssignment($assignment, $course);

        $data = $this->validated($request, $assignment);
        $submission = $this->assignments->submit($enrollment, $assignment, $data, $request->file('file'));

        return ApiResponse::success($submission, 'Assignment submitted.');
    }

    public function download(Request $request, Course $course, Assignment $assignment, AssignmentSubmission $submission): StreamedResponse
    {
        $enrollment = $this->enrollmentFor($request, $course);
        $this->guardAssignment($assignment, $course);
        abort_unless($submission->assignment_id === $assignment->id, 404);
        abort_unless($submission->enrollment_id === $enrollment->id, 404);
        abort_unless($submission->hasFile(), 404);

        return $this->assignments->disk()->download($submission->file_path, $submission->file_name);
    }

    private function enrollmentFor(Request $request, Course $course): Enrollment
    {
        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();

        $this->authorize('access', $enrollment);

        return $enrollment;
    }

    private function guardAssignment(Assignment $assignment, Course $course): void
    {
        abort_unless($assignment->course_id === $course->id, 404);
    }

    /** @return array{body: ?string, link_url: ?string} */
    private function validated(Request $request, Assignment $assignment): array
    {
        $rules = [];

        if ($assignment->acceptsType('text')) {
            $rules['body'] = 'nullable|string';
        }
        if ($assignment->acceptsType('link')) {
            $rules['link_url'] = 'nullable|url|max:2048';
        }
        if ($assignment->acceptsAnyFileType()) {
            $extensions = array_values(array_diff($assignment->allowedTypes(), ['text', 'link']));
            $rules['file'] = ['nullable', 'file', 'max:'.($assignment->max_file_mb * 1024)];
            if ($extensions) {
                $rules['file'][] = 'mimes:'.implode(',', $extensions);
            }
        }

        $data = $request->validate($rules);

        return ['body' => $data['body'] ?? null, 'link_url' => $data['link_url'] ?? null];
    }
}
