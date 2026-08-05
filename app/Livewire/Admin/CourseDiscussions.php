<?php

namespace App\Livewire\Admin;

use App\Models\Course;
use App\Models\Discussion;
use App\Services\Learning\DiscussionService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** The instructor's Q&A inbox for one course: reply (auto-badged "Instructor") and resolve. */
class CourseDiscussions extends Component
{
    public Course $course;

    public ?int $openThreadId = null;

    public string $reply = '';

    public function mount(Course $course): void
    {
        $this->course = $course;
    }

    public function openThread(int $id): void
    {
        $this->openThreadId = $id;
        $this->reply = '';
    }

    public function submitReply(DiscussionService $discussions): void
    {
        $this->validate(['reply' => 'required|string|max:5000']);

        $thread = Discussion::findOrFail($this->openThreadId);
        abort_unless($thread->course_id === $this->course->id, 404);

        $discussions->reply(auth()->user(), $thread, $this->reply);

        $this->reply = '';
        $this->openThreadId = null;
    }

    public function resolve(DiscussionService $discussions, int $id): void
    {
        $thread = Discussion::findOrFail($id);
        abort_unless($thread->course_id === $this->course->id, 404);

        $discussions->resolve($thread);
    }

    public function render(): View
    {
        $threads = $this->course->discussions()
            ->whereNull('parent_id')
            ->withCount('replies')
            ->with('user', 'lesson', 'replies.user')
            ->latest()
            ->get();

        return view('livewire.admin.course-discussions', ['threads' => $threads])
            ->layout('layouts.admin')
            ->title('Q&A '.$this->course->title);
    }
}
