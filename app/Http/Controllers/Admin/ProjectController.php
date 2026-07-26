<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        return view('admin.projects.index', [
            'projects' => Project::with('client')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.projects.form', [
            'project' => new Project,
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $project = Project::create($data + [
            'uuid' => (string) Str::uuid(),
            'project_number' => 'PRJ-'.now()->format('Y').'-'.str_pad((string) (Project::withTrashed()->count() + 1), 4, '0', STR_PAD_LEFT),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Project $project): View
    {
        return view('admin.projects.show', [
            'project' => $project->load(['client', 'tasks', 'notes.user', 'updates.user', 'documents']),
        ]);
    }

    public function edit(Project $project): View
    {
        return view('admin.projects.form', [
            'project' => $project,
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validated($request));

        return redirect()->route('admin.projects.show', $project)->with('success', 'Project updated.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('admin.projects.index')->with('success', 'Project deleted.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'client_id' => 'required|exists:clients,id',
            'category' => 'nullable|string|max:100',
            'status' => 'required|in:proposal,active,on_hold,completed,cancelled',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'completed_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:5',
        ]);
    }
}
