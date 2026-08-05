<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An optional WebVTT captions track for self-hosted video; YouTube's own captions already pass through the IFrame player once cc_load_policy is enabled. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('captions_url')->nullable()->after('video_disk_path');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('captions_url');
        });
    }
};
