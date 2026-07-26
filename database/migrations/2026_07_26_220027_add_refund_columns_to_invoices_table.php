<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §7.1 — mirrors the existing issued_by/issued_at pattern for a refund's own audit trail. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('refunded_by')->nullable()->after('issued_at')->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable()->after('refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn('refunded_at');
        });
    }
};
