<?php

namespace App\Http\Controllers\Student;

use App\Enums\LearningEventType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use App\Services\Learning\LearningEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Student-facing material download — mirrors DocumentService's private-disk, policy-gated stream. */
class LessonMaterialController extends Controller
{
    public function __construct(private readonly LearningEventRecorder $events) {}

    public function download(Request $request, Course $course, Lesson $lesson, LessonMaterial $material): StreamedResponse|RedirectResponse
    {
        abort_unless($lesson->module->course_id === $course->id, 404);
        abort_unless($material->lesson_id === $lesson->id, 404);

        $enrollment = Enrollment::where('user_id', $request->user()->id)
            ->where('course_id', $course->id)
            ->firstOrFail();
        $this->authorize('access', $enrollment);
        $this->authorize('view', [$lesson, $enrollment]);

        $this->events->record($enrollment, LearningEventType::MaterialDownloaded, $lesson, $material);

        if (Str::startsWith($material->file_path, 'http')) {
            return redirect()->away($material->file_path);
        }

        return Storage::disk('local')->download($material->file_path, $material->title);
    }
}
