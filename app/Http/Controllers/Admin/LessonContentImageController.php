<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Images uploaded into a markdown lesson's content. Stored on the same
 * private `local` disk as everything else in the LMS (DocumentService
 * convention); served back through a policy-gated route, never a public URL,
 * since the image lives inside paid/enrolled-only lesson content.
 */
class LessonContentImageController extends Controller
{
    public function store(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|image|max:5120',
        ]);

        $filename = Str::uuid().'.'.$data['image']->extension();
        $data['image']->storeAs("lesson-content-images/{$lesson->id}", $filename, 'local');

        return response()->json([
            'success' => true,
            'url' => route('learn.content-images.show', [$lesson->module->course, $lesson, $filename]),
        ]);
    }
}
