<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Services\Learning\GradebookService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The instructor's per-course grade matrix: students × items, plus a CSV export link. */
class GradeMatrix extends Component
{
    public Course $course;

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function render(GradebookService $gradebook): View
    {
        $items = [];
        foreach ($this->course->quizzes()->where('is_published', true)->get() as $quiz) {
            $items[] = ['type' => 'quiz', 'id' => $quiz->id, 'title' => $quiz->title];
        }
        foreach ($this->course->assignments()->where('is_published', true)->get() as $assignment) {
            $items[] = ['type' => 'assignment', 'id' => $assignment->id, 'title' => $assignment->title];
        }

        $enrollments = $this->course->enrollments()->whereIn('status', ['active', 'completed'])->with('user')->get();

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $gradesByKey = collect($gradebook->itemsFor($enrollment))->keyBy(fn (array $i) => $i['type'].'_'.$i['id']);

            $grades = [];
            foreach ($items as $item) {
                $grades[] = $gradesByKey->get($item['type'].'_'.$item['id'])['percent'] ?? null;
            }

            $rows[] = [
                'enrollment' => $enrollment,
                'grades' => $grades,
                'course_grade' => $gradebook->courseGradePercentFromItems($gradesByKey->values()->all()),
            ];
        }

        return view('livewire.admin.grade-matrix', ['items' => $items, 'rows' => $rows])
            ->layout('layouts.admin')
            ->title('Gradebook '.$this->course->title);
    }
}
