<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\CourseNotifyRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everyone waiting for a course to open.
 *
 * This is a sales list, not a report. Each row is somebody who read a whole
 * sales page, decided yes, and found nothing to buy: the warmest audience the
 * site has, and the one it could not previously see at all.
 *
 * So the two things it has to do well are grouping by course, which is the
 * order the launches happen in, and marking people as told, which is what
 * stops the second launch message going to the same person twice.
 */
class CourseWaitlist extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $courseId = '';

    #[Url(except: 'waiting')]
    public string $status = 'waiting';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    /** Mark one person as told, so the next launch message skips them. */
    public function markNotified(int $id): void
    {
        CourseNotifyRequest::whereKey($id)->update(['notified_at' => now()]);

        unset($this->counts);
    }

    /** Everyone still waiting on one course, in one go, on the day it opens. */
    public function markCourseNotified(int $courseId): void
    {
        CourseNotifyRequest::where('course_id', $courseId)->waiting()->update(['notified_at' => now()]);

        unset($this->counts);
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'total' => CourseNotifyRequest::count(),
            'waiting' => CourseNotifyRequest::waiting()->count(),
            'courses' => CourseNotifyRequest::distinct()->count('course_id'),
        ];
    }

    /** Courses ranked by how many people are waiting: the launch order. */
    #[Computed]
    public function demand()
    {
        return CourseNotifyRequest::query()
            ->selectRaw('course_id, COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN notified_at IS NULL THEN 1 ELSE 0 END) AS waiting')
            ->groupBy('course_id')
            ->orderByDesc('total')
            ->with('course:id,title,slug,is_coming_soon')
            ->limit(12)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.course-waitlist', [
            'rows' => CourseNotifyRequest::query()
                ->with('course:id,title,slug')
                ->when($this->status === 'waiting', fn (Builder $q) => $q->waiting())
                ->when($this->status === 'notified', fn (Builder $q) => $q->whereNotNull('notified_at'))
                ->when($this->courseId !== '', fn (Builder $q) => $q->where('course_id', $this->courseId))
                ->when($this->q !== '', function (Builder $query) {
                    $term = '%'.$this->q.'%';
                    $query->where(fn (Builder $q) => $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('whatsapp', 'like', $term));
                })
                ->latest()
                ->paginate(40),
            'courses' => Course::orderBy('title')->get(['id', 'title']),
        ])->layout('layouts.admin')->title('Course waitlist');
    }
}
