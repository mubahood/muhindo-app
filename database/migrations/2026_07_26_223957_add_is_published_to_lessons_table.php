<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A per-lesson publish toggle so a lesson can be built in the curriculum tree without
 * being visible to students yet. Defaults true so every existing lesson stays visible exactly
 * as it already was, this migration changes nothing about current student-facing behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });
    }
};
