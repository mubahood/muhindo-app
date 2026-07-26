<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** §7.5 — one AJAX endpoint for the curriculum builder's drag-and-drop: modules and/or lessons, in one transaction. */
class CurriculumController extends Controller
{
    public function reorder(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'modules' => 'nullable|array',
            'modules.*.id' => 'required|integer',
            'modules.*.sort_order' => 'required|integer|min:0',
            'lessons' => 'nullable|array',
            'lessons.*.id' => 'required|integer',
            'lessons.*.sort_order' => 'required|integer|min:0',
            'lessons.*.course_module_id' => 'required|integer',
        ]);

        $moduleIds = CourseModule::where('course_id', $course->id)->pluck('id');
        $lessonIds = Lesson::whereIn('course_module_id', $moduleIds)->pluck('id');

        DB::transaction(function () use ($data, $moduleIds, $lessonIds) {
            foreach ($data['modules'] ?? [] as $row) {
                if ($moduleIds->contains($row['id'])) {
                    CourseModule::whereKey($row['id'])->update(['sort_order' => $row['sort_order']]);
                }
            }

            foreach ($data['lessons'] ?? [] as $row) {
                if ($lessonIds->contains($row['id']) && $moduleIds->contains($row['course_module_id'])) {
                    Lesson::whereKey($row['id'])->update([
                        'sort_order' => $row['sort_order'],
                        'course_module_id' => $row['course_module_id'],
                    ]);
                }
            }
        });

        return response()->json(['success' => true]);
    }
}
