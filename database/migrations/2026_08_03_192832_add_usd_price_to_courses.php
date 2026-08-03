<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second, separately-set price in USD.
 *
 * Not a conversion. A live FX rate would make the price on the page depend on
 * a third-party API being up, and would quietly move what a student is charged
 * between the moment they read it and the moment they pay. The owner sets both
 * numbers, and both are stable until the owner changes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->decimal('price_usd', 10, 2)->nullable()->after('price'));
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $t) => $t->dropColumn('price_usd'));
    }
};
