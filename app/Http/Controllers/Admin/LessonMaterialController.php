<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LessonMaterialController extends Controller
{
    public function store(Request $request, Lesson $lesson): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'type' => 'required|in:pdf,zip,link,file',
            'url' => 'nullable|url|max:500',
            'file' => 'nullable|file|max:20480',
        ]);

        $path = $data['url'] ?? null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->storeAs(
                'lesson-materials/'.$lesson->id,
                Str::uuid().'.'.$request->file('file')->getClientOriginalExtension(),
                'local'
            );
        }

        if (! $path) {
            return back()->with('error', 'Provide either a file or a URL.');
        }

        $lesson->materials()->create([
            'title' => $data['title'],
            'type' => $data['type'],
            'file_path' => $path,
        ]);

        return redirect()->route('admin.courses.show', $lesson->module->course)->with('success', 'Material added.');
    }

    public function destroy(LessonMaterial $material): RedirectResponse
    {
        $course = $material->lesson->module->course;

        if (! Str::startsWith($material->file_path, 'http')) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();

        return redirect()->route('admin.courses.show', $course)->with('success', 'Material removed.');
    }
}
