<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §6.2/P5.3 — an optional self-hosted alternative to `video_url` (YouTube/Vimeo). Streamed via a signed, time-limited URL rather than a public path. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_disk_path')->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('video_disk_path');
        });
    }
};
