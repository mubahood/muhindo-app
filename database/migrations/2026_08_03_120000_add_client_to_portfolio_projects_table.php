<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who each system was built for.
 *
 * The column was added to the live database by hand and to the model's
 * $fillable, but never to a migration — so it existed in production and
 * nowhere else. A fresh clone could not insert a project at all.
 *
 * Guarded, because the live database already has it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('portfolio_projects', 'client')) {
            return;
        }

        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->string('client')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropColumn('client');
        });
    }
};
