<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where the client is, which decides time zones, currency and how we meet. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->string('country', 80)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('project_inquiries', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
