<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson completion requirements, all enforced server-side in ProgressService:
 * - lessons.min_active_seconds — a student must spend at least this much focused
 *   time (the active_seconds tracker) on the lesson before they can complete it.
 * - quizzes/assignments.is_required — a compulsory activity blocks completing its
 *   lesson until the student has submitted it; optional ones never block.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedInteger('min_active_seconds')->nullable()->after('duration_minutes');
        });
        Schema::table('quizzes', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('counts_toward_certificate');
        });
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('resubmit_until_graded');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('min_active_seconds'));
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn('is_required'));
        Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn('is_required'));
    }
};
