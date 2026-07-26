<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Learning\GradebookService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** §5.4 — CSV export of the per-course grade matrix. */
class GradebookExportController extends Controller
{
    public function __invoke(Course $course, GradebookService $gradebook): StreamedResponse
    {
        $items = $course->quizzes()->where('is_published', true)->get()
            ->map(fn ($q) => ['type' => 'quiz', 'id' => $q->id, 'title' => $q->title])
            ->concat($course->assignments()->where('is_published', true)->get()
                ->map(fn ($a) => ['type' => 'assignment', 'id' => $a->id, 'title' => $a->title]))
            ->values();

        $enrollments = $course->enrollments()->whereIn('status', ['active', 'completed'])->with('user')->get();

        $filename = Str::slug($course->title).'-gradebook.csv';

        return response()->streamDownload(function () use ($items, $enrollments, $gradebook) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student', ...$items->pluck('title')->all(), 'Course grade']);

            foreach ($enrollments as $enrollment) {
                $grades = collect($gradebook->itemsFor($enrollment))->keyBy(fn ($i) => $i['type'].'_'.$i['id']);
                $row = [$enrollment->user->name];
                foreach ($items as $item) {
                    $percent = $grades->get($item['type'].'_'.$item['id'])['percent'] ?? null;
                    $row[] = $percent !== null ? $percent : '';
                }
                $row[] = $gradebook->courseGradePercentFromItems($grades->values()->all()) ?? '';
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
