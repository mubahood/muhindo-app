<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PUBLIC_SITE_PLAN.md §2.2/§2.3 — public catalogue card + sales-page fields. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('tagline', 160)->nullable()->after('description');
            $table->json('outcomes')->nullable()->after('tagline');
            $table->json('requirements')->nullable()->after('outcomes');
            $table->string('cover_alt')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'outcomes', 'requirements', 'cover_alt']);
        });
    }
};
