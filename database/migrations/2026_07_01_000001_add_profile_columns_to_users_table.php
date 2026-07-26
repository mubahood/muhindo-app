<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared profile columns for every account type on the platform (owner,
 * admin, student, client). `role` mirrors the Spatie role for fast checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('role', 32)->default('student')->index()->after('password');
            $table->boolean('is_admin')->default(false)->after('role');
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->string('phone', 32)->nullable()->after('is_active');
            $table->text('bio')->nullable()->after('phone');
            $table->string('avatar')->nullable()->after('bio');
            $table->string('theme', 8)->default('light')->after('avatar');
            $table->timestamp('last_active_at')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'role', 'is_admin', 'is_active',
                'phone', 'bio', 'avatar', 'theme', 'last_active_at',
            ]);
        });
    }
};
