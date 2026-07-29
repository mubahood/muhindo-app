<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account capabilities: a person can learn AND hire at the same time. The single
 * `role` column couldn't express that ("student" OR "client"), so learning and
 * client access become independent flags any account can hold.
 *
 * `role` stays as the account's primary role — it still drives admin access and
 * the Spatie role sync — but student/client access is now read from these flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_student')->default(false)->after('role');
            $table->boolean('is_client')->default(false)->after('is_student');
        });

        // Backfill from the existing single role so nobody loses access.
        DB::table('users')->where('role', 'student')->update(['is_student' => true]);
        DB::table('users')->where('role', 'client')->update(['is_client' => true]);

        // Anyone already enrolled in a course is a learner, whatever their role says.
        DB::table('users')->whereIn('id', fn ($q) => $q->select('user_id')->from('enrollments'))
            ->update(['is_student' => true]);

        // Anyone with a client record can reach the client portal.
        DB::table('users')->whereIn('id', fn ($q) => $q->select('user_id')->from('clients')->whereNotNull('user_id'))
            ->update(['is_client' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_student', 'is_client']);
        });
    }
};
