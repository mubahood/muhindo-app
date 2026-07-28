<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Total focused time a student has spent on a lesson (reading OR watching) — distinct
 * from watch_seconds, which only accumulates while a video is actually playing. Fed by
 * the focus-gated frontend timer, clamped server-side like the video heartbeat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->unsignedInteger('active_seconds')->default(0)->after('watch_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropColumn('active_seconds');
        });
    }
};
