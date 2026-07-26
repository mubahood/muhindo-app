<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §4.4 — started_at + last_position_seconds power in-lesson resume once the player heartbeat exists. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('lesson_id');
            $table->unsignedInteger('last_position_seconds')->default(0)->after('watch_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'last_position_seconds']);
        });
    }
};
