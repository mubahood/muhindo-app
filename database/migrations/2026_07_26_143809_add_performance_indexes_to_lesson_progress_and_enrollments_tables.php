<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->index(['lesson_id', 'completed_at']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex(['lesson_id', 'completed_at']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
