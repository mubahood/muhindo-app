<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §4.4/§6.4 — optional per-course access window (days) applied to `enrollments.expires_at` at enrollment/activation time. Null = lifetime access, the default (no behavior change for existing courses). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedInteger('access_duration_days')->nullable()->after('progression');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('access_duration_days');
        });
    }
};
