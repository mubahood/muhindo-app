<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a course may be bought in instalments.
 *
 * Off by default, and deliberately per course rather than per catalogue. A
 * crash course at UGX 40,000 split four ways is four payments of ten thousand
 * for a fortnight's work, which is friction with nothing bought by it. The
 * courses worth splitting are the long, expensive, finished ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('allows_instalments')->default(false)->after('price_usd');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('allows_instalments');
        });
    }
};
