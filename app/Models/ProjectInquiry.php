<?php

namespace App\Models;

use App\Enums\ProjectInquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** §4.3 — a public "start a project" lead. */
class ProjectInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'name', 'email', 'phone', 'organisation', 'project_type',
        'budget_range', 'timeline', 'description', 'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectInquiryStatus::class,
        ];
    }
}
