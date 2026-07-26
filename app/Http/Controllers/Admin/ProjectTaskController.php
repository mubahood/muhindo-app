<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $project->tasks()->create($data + [
            'status' => 'todo',
            'created_by' => $request->user()->id,
            'sort_order' => $project->tasks()->count(),
        ]);

        return back()->with('success', 'Task added.');
    }

    public function update(Request $request, Project $project, ProjectTask $task): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:todo,doing,done',
        ]);

        $task->update($data + ['completed_at' => $data['status'] === 'done' ? now() : null]);

        return back()->with('success', 'Task updated.');
    }

    public function destroy(Project $project, ProjectTask $task): RedirectResponse
    {
        $task->delete();

        return back()->with('success', 'Task removed.');
    }
}
