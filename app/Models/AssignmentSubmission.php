<?php

namespace App\Models;

use App\Enums\AssignmentSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssignmentSubmission extends Model
{
    use LogsActivity;

    protected $fillable = [
        'uuid', 'assignment_id', 'enrollment_id', 'attempt_no', 'body', 'link_url',
        'file_path', 'file_name', 'file_size', 'file_mime', 'status', 'submitted_at',
        'is_late', 'points_awarded', 'feedback', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssignmentSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'points_awarded' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    /** Grades are auditable: the grade itself and the status transition (submitted → returned), not draft edits. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'points_awarded', 'graded_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('grading');
    }
}
