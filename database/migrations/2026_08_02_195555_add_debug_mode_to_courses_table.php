<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An authoring switch that lifts the pacing gates on a course.
 *
 * Building a course means walking it end to end repeatedly, and every walk
 * currently costs the full minimum screen time on every lesson plus a
 * submission for every required quiz and assignment. This turns those off for
 * one course so it can be stepped through.
 *
 * Deliberately per-course rather than global or per-environment: it has to be
 * usable on the real site against real content, which is the only place the
 * pacing is worth checking. It is nulled on nothing and defaults to off, so an
 * existing course is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('debug_mode')->default(false)->after('progression');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('debug_mode');
        });
    }
};
