<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'is_confidential' => 'nullable|boolean',
            'file' => 'required|file|max:20480',
        ]);

        $this->documents->store(
            $project,
            $request->file('file'),
            $data['title'],
            $data['category'] ?? null,
            $request->boolean('is_confidential'),
            $request->user()->id,
        );

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Project $project, ProjectDocument $document): StreamedResponse
    {
        abort_unless($document->project_id === $project->id, 404);

        return $this->documents->disk()->download($document->file_path, $document->file_name);
    }

    public function destroy(Project $project, ProjectDocument $document): RedirectResponse
    {
        abort_unless($document->project_id === $project->id, 404);

        $this->documents->delete($document);

        return back()->with('success', 'Document removed.');
    }
}
