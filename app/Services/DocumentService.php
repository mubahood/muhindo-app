<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Project document storage. Files live on the *private* `local` disk under a
 * per-project path, never web-served directly; downloads stream through
 * ProjectDocumentController after a Policy check.
 */
class DocumentService
{
    private const DISK = 'local';

    public function store(Project $project, UploadedFile $file, string $title, ?string $category, bool $confidential, ?int $uploadedBy = null): ProjectDocument
    {
        $dir = "projects/{$project->id}";
        $name = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($dir, $name, self::DISK);

        return ProjectDocument::create([
            'project_id' => $project->id,
            'title' => $title,
            'category' => $category,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'is_confidential' => $confidential,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(self::DISK);
    }

    public function delete(ProjectDocument $document): void
    {
        if ($this->disk()->exists($document->file_path)) {
            $this->disk()->delete($document->file_path);
        }
        $document->delete();
    }
}
