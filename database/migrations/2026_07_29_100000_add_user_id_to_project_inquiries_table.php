<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a project request back to the account that submitted it, so a signed-in
 * client can follow their own request in the portal instead of it disappearing
 * into the admin inbox. Stays nullable — guests can still request a project
 * without an account, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('uuid')->constrained()->nullOnDelete();
        });

        // Backfill by email so existing requests attach to matching accounts.
        DB::statement('UPDATE project_inquiries SET user_id = (SELECT id FROM users WHERE users.email = project_inquiries.email LIMIT 1) WHERE user_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
