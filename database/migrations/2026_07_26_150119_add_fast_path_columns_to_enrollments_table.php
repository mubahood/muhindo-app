<?php

use App\Models\Enrollment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // Denormalized fast-path. Every list/dashboard view reads these
            // Instead of recomputing from lesson_progress, which does not hold up at scale.
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedInteger('total_watch_seconds')->default(0)->after('progress_percent');
            $table->foreignId('last_lesson_id')->nullable()->after('total_watch_seconds')
                ->constrained('lessons')->nullOnDelete();
            $table->timestamp('last_accessed_at')->nullable()->after('last_lesson_id');

            $table->index('last_accessed_at');
        });

        // Backfill: only progress_percent is computable from data that already
        // exists (lesson_progress). total_watch_seconds/last_lesson_id/
        // last_accessed_at have no historical source, they start populating from
        // the next lesson view/completion onward.
        Enrollment::withCount(['progressRecords as completed_lessons_count' => fn ($query) => $query->whereNotNull('completed_at')])
            ->with(['course' => fn ($query) => $query->withCount('lessons')])
            ->each(function (Enrollment $enrollment) {
                $enrollment->update(['progress_percent' => $enrollment->progressPercent()]);
            });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_lesson_id');
            $table->dropColumn(['progress_percent', 'total_watch_seconds', 'last_accessed_at']);
        });
    }
};
