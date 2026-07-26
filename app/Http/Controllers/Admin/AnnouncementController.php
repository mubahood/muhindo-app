<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/** §7.3 — Classroom's stream: draft or publish-immediately; publishing later is a separate, one-time action. */
class AnnouncementController extends Controller
{
    public function create(Course $course): View
    {
        return view('admin.announcements.form', ['course' => $course, 'announcement' => new Announcement]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $data = $this->validated($request);
        $publishNow = $request->boolean('publish_now');

        $announcement = $course->announcements()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => $publishNow ? now() : null,
        ]);

        if ($publishNow) {
            $this->notifyStudents($announcement);
        }

        return redirect()->route('admin.courses.show', $course)->with('success', 'Announcement created.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', ['course' => $announcement->course, 'announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);
        $announcement->update(['title' => $data['title'], 'body' => $data['body']]);

        return redirect()->route('admin.courses.show', $announcement->course)->with('success', 'Announcement updated.');
    }

    /** Publishing is a one-time, explicit action — never re-triggered by an unrelated edit. */
    public function publish(Announcement $announcement): RedirectResponse
    {
        if ($announcement->isPublished()) {
            return redirect()->route('admin.courses.show', $announcement->course)->with('error', 'This announcement is already published.');
        }

        $announcement->update(['published_at' => now()]);
        $this->notifyStudents($announcement);

        return redirect()->route('admin.courses.show', $announcement->course)->with('success', 'Announcement published.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $course = $announcement->course;
        $announcement->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Announcement deleted.');
    }

    /** @return array{title: string, body: string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'body' => 'required|string',
        ]);
    }

    private function notifyStudents(Announcement $announcement): void
    {
        $userIds = $announcement->course->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->pluck('user_id');

        Notification::send(User::whereIn('id', $userIds)->get(), new AnnouncementPublishedNotification($announcement));
    }
}
