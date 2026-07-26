<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    protected $fillable = ['uuid', 'enrollment_id', 'certificate_no', 'issued_at', 'pdf_path'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    /** Addressed by uuid everywhere (verify page, PDF stream) — matches Invoice's convention. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
