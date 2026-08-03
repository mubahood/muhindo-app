<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a case study actually needs to say.
 *
 * The page had a one-line description and a bullet list, which answers "what
 * is it called" but not "what was broken", "how does it work" or "what did it
 * have to survive" — the three things a ministry procurement officer is
 * actually reading for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->text('problem')->nullable()->after('description');
            $table->text('approach')->nullable()->after('problem');
            $table->json('mechanics')->nullable()->after('approach');   // how it works, step by step
            $table->json('stack')->nullable()->after('mechanics');      // what it is built from
            $table->json('constraints')->nullable()->after('stack');    // what it had to survive
            $table->string('role')->nullable()->after('constraints');
            $table->string('period')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_projects', function (Blueprint $table) {
            $table->dropColumn(['problem', 'approach', 'mechanics', 'stack',
                'constraints', 'role', 'period']);
        });
    }
};
