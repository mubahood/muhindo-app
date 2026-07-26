<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A billing document raised against either a client (project work) or a user
 * (a course purchase) — billable_type/billable_id covers both. Monetary
 * columns are decimal(12,2); amount_paid/balance are roll-ups kept in step
 * with the append-only payments rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('invoice_no')->unique();
            $table->nullableMorphs('billable'); // Client (project billing) or User (course purchase)
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();

            $table->string('currency', 5)->default('UGX');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);

            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
