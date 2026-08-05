<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The client-visible progress log. This is the client's window into project work. */
class ProjectUpdateController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'update_text' => 'required|string',
            'percent_complete' => 'nullable|integer|min:0|max:100',
        ]);

        $project->updates()->create([
            'user_id' => $request->user()->id,
            'update_text' => $data['update_text'],
            'percent_complete' => $data['percent_complete'] ?? null,
        ]);

        return back()->with('success', 'Update posted, visible to the client.');
    }
}
