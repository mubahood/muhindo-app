<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectNoteController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'note' => 'required|string',
            'is_client_visible' => 'nullable|boolean',
        ]);

        $project->notes()->create([
            'user_id' => $request->user()->id,
            'note' => $data['note'],
            'is_client_visible' => $request->boolean('is_client_visible'),
        ]);

        return back()->with('success', 'Note added.');
    }
}
