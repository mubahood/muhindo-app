<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Keeps the instructor's teaching checklist separate from student completion. */
class LessonTeachingProgressController extends Controller
{
    public function updateLesson(Request $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validate(['taught' => 'required|boolean']);
        $now = now();

        if ($request->boolean('taught')) {
            DB::table('lesson_teaching_progress')->updateOrInsert(
                ['user_id' => $request->user()->id, 'lesson_id' => $lesson->id],
                ['taught_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            );
            $message = 'Topic marked as taught.';
        } else {
            DB::table('lesson_teaching_progress')
                ->where('user_id', $request->user()->id)
                ->where('lesson_id', $lesson->id)
                ->delete();
            $message = 'Topic removed from your taught list.';
        }

        return back()->with('success', $message);
    }

    public function updateCourse(Request $request, Course $course): RedirectResponse
    {
        $request->validate(['taught' => 'required|boolean']);
        $lessonIds = $course->lessons()->pluck('lessons.id');
        $userId = $request->user()->id;

        DB::transaction(function () use ($request, $lessonIds, $userId): void {
            if (! $request->boolean('taught')) {
                DB::table('lesson_teaching_progress')->where('user_id', $userId)->whereIn('lesson_id', $lessonIds)->delete();

                return;
            }

            $now = now();
            foreach ($lessonIds as $lessonId) {
                DB::table('lesson_teaching_progress')->updateOrInsert(
                    ['user_id' => $userId, 'lesson_id' => $lessonId],
                    ['taught_at' => $now, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        });

        return back()->with('success', $request->boolean('taught')
            ? 'All published topics marked as taught.'
            : 'Course teaching progress cleared.');
    }
}
