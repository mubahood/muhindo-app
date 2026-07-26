<?php

namespace App\Services\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * §5.1/§5.3 — the Classroom-style turn-in flow: draft (private, editable) -> submitted ->
 * returned (graded, P3.7). Files live on the private `local` disk, same convention as
 * ProjectDocumentController/DocumentService — never web-served directly.
 */
class AssignmentService
{
    private const DISK = 'local';

    /** @param  array{body?: ?string, link_url?: ?string}  $data */
    public function saveDraft(Enrollment $enrollment, Assignment $assignment, array $data, ?UploadedFile $file): AssignmentSubmission
    {
        $submission = $this->workingSubmission($enrollment, $assignment);
        $this->applyData($submission, $assignment, $data, $file);
        $submission->status = AssignmentSubmissionStatus::Draft;
        $submission->save();

        return $submission;
    }

    /** @param  array{body?: ?string, link_url?: ?string}  $data */
    public function submit(Enrollment $enrollment, Assignment $assignment, array $data, ?UploadedFile $file): AssignmentSubmission
    {
        $isLate = $assignment->isPastDue();
        if ($isLate && ! $assignment->allow_late) {
            throw new HttpException(422, 'This assignment is closed — the due date has passed.');
        }

        $submission = $this->workingSubmission($enrollment, $assignment);
        $this->applyData($submission, $assignment, $data, $file);
        $submission->status = AssignmentSubmissionStatus::Submitted;
        $submission->submitted_at = now();
        $submission->is_late = $isLate;
        $submission->save();

        return $submission;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Resolves the row a new draft-save/submit call should write to: the existing draft if one
     * is still open, otherwise a brand-new attempt — incrementing attempt_no only when the
     * previous attempt has already left draft state (a genuine resubmission), never on every
     * autosave. Blocks further writes once the latest attempt is submitted/returned per
     * resubmit_until_graded — once returned (graded), the door is closed regardless of the flag.
     */
    private function workingSubmission(Enrollment $enrollment, Assignment $assignment): AssignmentSubmission
    {
        Gate::authorize('access', $enrollment);

        if ($assignment->course_id !== $enrollment->course_id) {
            throw new HttpException(404);
        }

        $latest = AssignmentSubmission::where('assignment_id', $assignment->id)
            ->where('enrollment_id', $enrollment->id)
            ->latest('attempt_no')
            ->first();

        if ($latest === null) {
            return new AssignmentSubmission([
                'uuid' => (string) Str::uuid(),
                'assignment_id' => $assignment->id,
                'enrollment_id' => $enrollment->id,
                'attempt_no' => 1,
            ]);
        }

        if ($latest->status === AssignmentSubmissionStatus::Draft) {
            return $latest;
        }

        if ($latest->status === AssignmentSubmissionStatus::Returned) {
            throw new HttpException(409, 'This assignment has already been graded — no further submissions are accepted.');
        }

        if (! $assignment->resubmit_until_graded) {
            throw new HttpException(409, 'This assignment has already been submitted and is awaiting grading.');
        }

        return new AssignmentSubmission([
            'uuid' => (string) Str::uuid(),
            'assignment_id' => $assignment->id,
            'enrollment_id' => $enrollment->id,
            'attempt_no' => $latest->attempt_no + 1,
        ]);
    }

    /** @param  array{body?: ?string, link_url?: ?string}  $data */
    private function applyData(AssignmentSubmission $submission, Assignment $assignment, array $data, ?UploadedFile $file): void
    {
        $submission->body = $assignment->acceptsType('text') ? ($data['body'] ?? null) : null;
        $submission->link_url = $assignment->acceptsType('link') ? ($data['link_url'] ?? null) : null;

        if ($file && $assignment->acceptsAnyFileType()) {
            if ($submission->file_path && $this->disk()->exists($submission->file_path)) {
                $this->disk()->delete($submission->file_path);
            }

            $dir = "assignments/{$assignment->id}/{$submission->enrollment_id}";
            $name = Str::uuid().'.'.$file->getClientOriginalExtension();

            $submission->file_path = $file->storeAs($dir, $name, self::DISK);
            $submission->file_name = $file->getClientOriginalName();
            $submission->file_size = $file->getSize();
            $submission->file_mime = $file->getClientMimeType();
        }
    }
}
