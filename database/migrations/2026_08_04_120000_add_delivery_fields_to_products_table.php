<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a buyer gets, and what they do with it once they have it.
 *
 * A source-code product is not an e-book: somebody who has paid still has to
 * get it running, and "here is a zip, good luck" is where most code sales turn
 * into support requests. These columns carry the answer with the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('whats_inside')->nullable()->after('description');   // what is in the archive
            $table->json('stack')->nullable()->after('whats_inside');         // what it is written in
            $table->json('requirements')->nullable()->after('stack');         // what you need to run it
            $table->text('install_guide')->nullable()->after('requirements'); // markdown, step by step
            $table->string('demo_url')->nullable()->after('external_url');
            $table->string('version')->nullable()->after('demo_url');
            $table->string('license_terms')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['whats_inside', 'stack', 'requirements', 'install_guide',
                'demo_url', 'version', 'license_terms']);
        });
    }
};
