<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only payment against an invoice. balance_after snapshots the
 * invoice balance immediately after this payment, so the ledger is auditable
 * without recomputing history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('method', 16);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['invoice_id', 'created_at'], 'payment_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
