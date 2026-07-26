<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * §6.3.1 — the instructor's "Course → Students" workhorse: one row per
 * enrollment, sortable/filterable, searchable by student. Grade-to-date,
 * quiz average, and missing assignments are deferred to P3 (no quiz/
 * assignment models exist yet); everything else here reads real data.
 */
class CourseStudents extends Component
{
    use WithPagination;

    public Course $course;

    #[Url]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url]
    public string $sortField = 'progress_percent';

    #[Url]
    public string $sortDir = 'desc';

    private const SORTABLE = ['progress_percent', 'total_watch_seconds', 'last_accessed_at', 'enrolled_at', 'status'];

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $field = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'progress_percent';

        $enrollments = Enrollment::where('course_id', $this->course->id)
            ->with(['user', 'lastLesson'])
            ->when($this->search !== '', function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy($field, $this->sortDir)
            ->paginate(20);

        return view('livewire.admin.course-students', ['enrollments' => $enrollments])
            ->layout('layouts.admin')
            ->title($this->course->title.' — Students');
    }
}
