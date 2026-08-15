<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The day, on one screen.
 *
 * Before this existed, seeing a day's work meant opening every project in turn
 * and holding the dates in your head, because the dashboard's task list showed
 * only tasks with no project attached. A planning tool whose plan cannot be
 * read in one place does not help anybody plan.
 *
 * The order of the sections is the whole design:
 *
 *   overdue    first, and red. A missed task that scrolls out of sight is how
 *              a plan quietly dies, one day at a time.
 *   today      what was actually promised for today.
 *   next 7     enough to see what is coming, not enough to start it early.
 *   undated    the inbox, last. Captured, not yet placed.
 */
class Today extends Component
{
    /** Quick capture: one field, so a promise made on a call survives it. */
    public string $newTask = '';

    public ?int $newTaskProject = null;

    public function render(): View
    {
        return view('livewire.admin.today')
            ->layout('layouts.admin')
            ->title('Today');
    }

    /** @return Collection<int, ProjectTask> */
    #[Computed]
    public function overdue(): Collection
    {
        return $this->base()->overdue()->get();
    }

    /** @return Collection<int, ProjectTask> */
    #[Computed]
    public function today(): Collection
    {
        return $this->base()->dueOn(Carbon::today())->get();
    }

    /**
     * The coming week, grouped by day, today excluded.
     *
     * @return Collection<string, Collection<int, ProjectTask>>
     */
    #[Computed]
    public function upcoming(): Collection
    {
        return $this->base()
            ->open()
            ->whereDate('due_date', '>', Carbon::today())
            ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
            ->get()
            ->groupBy(fn (ProjectTask $task) => $task->due_date->toDateString());
    }

    /** @return Collection<int, ProjectTask> */
    #[Computed]
    public function undated(): Collection
    {
        return $this->base()->open()->whereNull('due_date')->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    #[Computed]
    public function contactHealth(): Collection
    {
        return app(DashboardService::class)->clientContactHealth(8);
    }

    /** Projects offered in the capture box, and used to label rows. */
    #[Computed]
    public function projects(): Collection
    {
        return Project::whereIn('status', ['proposal', 'active'])
            ->with('client')->orderBy('title')->get();
    }

    #[Computed]
    public function doneToday(): int
    {
        return ProjectTask::actionable()->whereDate('completed_at', Carbon::today())->count();
    }

    /** Tick a task off, or put it back. */
    public function toggle(int $id): void
    {
        $task = ProjectTask::actionable()->findOrFail($id);

        $done = ! $task->isDone();

        $task->update([
            'status' => $done ? 'done' : 'todo',
            'completed_at' => $done ? now() : null,
        ]);

        // Every list on this page is derived from the tasks table, so they all
        // have to be recomputed rather than just the one that changed.
        $this->forgetLists();
    }

    public function addTask(): void
    {
        $title = trim($this->newTask);

        // An empty capture box is a stray keypress, not a task.
        if ($title === '') {
            return;
        }

        ProjectTask::create([
            'project_id' => $this->newTaskProject ?: null,
            'title' => mb_substr($title, 0, 190),
            'status' => 'todo',
            'priority' => 'normal',
            // Deliberately undated. Forcing a date at capture time is what
            // makes people not capture; it lands in the inbox and gets placed
            // when there is a moment to think about it.
            'due_date' => null,
            'created_by' => auth()->id(),
            'assigned_to' => auth()->id(),
            'sort_order' => 0,
        ]);

        $this->newTask = '';
        $this->forgetLists();
    }

    /** Move a task to today, from overdue or from the inbox. */
    public function pullToToday(int $id): void
    {
        ProjectTask::actionable()->findOrFail($id)->update(['due_date' => Carbon::today()]);

        $this->forgetLists();
    }

    public function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            'high' => 'High',
            'low' => 'Low',
            default => '',
        };
    }

    /** @return \Illuminate\Database\Eloquent\Builder<ProjectTask> */
    private function base()
    {
        return ProjectTask::query()
            ->with('project.client')
            // High first, then the earliest date, then the order they were
            // planned in. sort_order alone put day 14 above day 1.
            //
            // A CASE rather than MySQL's FIELD(): FIELD() does not exist in
            // SQLite, which is what the test suite runs on, so it would work
            // in production and fail in the only place that could have caught
            // it being wrong.
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('sort_order');
    }

    private function forgetLists(): void
    {
        foreach (['overdue', 'today', 'upcoming', 'undated', 'doneToday', 'contactHealth'] as $list) {
            unset($this->{$list});
        }
    }
}
