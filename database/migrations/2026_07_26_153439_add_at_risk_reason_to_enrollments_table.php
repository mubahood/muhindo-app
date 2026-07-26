<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §6.4 — written nightly by app:detect-at-risk-enrollments; null means not at risk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('at_risk_reason', 16)->nullable()->after('total_watch_seconds');
            $table->index('at_risk_reason');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['at_risk_reason']);
            $table->dropColumn('at_risk_reason');
        });
    }
};
