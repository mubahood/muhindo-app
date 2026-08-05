<?php

namespace App\Services\Learning;

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\LearningEventType;
use App\Events\Learning\AssignmentSubmitted;
use App\Events\Learning\SubmissionGraded;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The Classroom-style turn-in flow: draft (private, editable) -> submitted ->
 * returned (graded). Files live on the private `local` disk, same convention as
 * ProjectDocumentController/DocumentService, never web-served directly.
 */
class AssignmentService
{
    private const DISK = 'local';

    public function __construct(private readonly LearningEventRecorder $events) {}

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
            throw new HttpException(422, 'This assignment is closed. The due date has passed.');
        }

        $submission = $this->workingSubmission($enrollment, $assignment);
        $this->applyData($submission, $assignment, $data, $file);
        $submission->status = AssignmentSubmissionStatus::Submitted;
        $submission->submitted_at = now();
        $submission->is_late = $isLate;
        $submission->save();

        $this->events->record($enrollment, LearningEventType::AssignmentSubmitted, $assignment->lesson, $submission, [
            'assignment_id' => $assignment->id, 'attempt_no' => $submission->attempt_no, 'is_late' => $isLate,
        ]);
        AssignmentSubmitted::dispatch($submission->fresh());

        return $submission;
    }

    /** An instructor grades and returns a submitted assignment. */
    public function return(AssignmentSubmission $submission, float $points, ?string $feedback, User $grader): AssignmentSubmission
    {
        if ($submission->status !== AssignmentSubmissionStatus::Submitted) {
            throw new HttpException(409, 'This submission is not awaiting grading.');
        }

        $max = (float) $submission->assignment->points;
        if ($points < 0 || $points > $max) {
            throw new HttpException(422, "Points must be between 0 and {$max}.");
        }

        $submission->update([
            'points_awarded' => $points,
            'feedback' => $feedback,
            'status' => AssignmentSubmissionStatus::Returned,
            'graded_by' => $grader->id,
            'graded_at' => now(),
        ]);

        $submission = $submission->fresh();
        SubmissionGraded::dispatch($submission);

        return $submission;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Resolves the row a new draft-save/submit call should write to: the existing draft if one
     * is still open, otherwise a brand-new attempt, incrementing attempt_no only when the
     * previous attempt has already left draft state (a genuine resubmission), never on every
     * autosave. Blocks further writes once the latest attempt is submitted/returned per
     * resubmit_until_graded, once returned (graded), the door is closed regardless of the flag.
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
            throw new HttpException(409, 'This assignment has already been graded. No further submissions are accepted.');
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
