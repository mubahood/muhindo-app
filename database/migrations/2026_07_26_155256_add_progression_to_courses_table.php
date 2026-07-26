<?php

use App\Enums\CourseProgression;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §4.3 — free (any order) vs sequential (locked until the previous lesson completes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('progression', 16)->default(CourseProgression::Free->value)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('progression');
        });
    }
};
