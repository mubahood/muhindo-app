<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records what the buyer said they intend to do about an unpaid invoice.
 *
 * Neither column changes what the invoice IS — an invoice with
 * `direct_payment_at` set is still Issued, still payable online, and still
 * grants nothing until a Payment actually clears the balance. They exist so a
 * buyer can leave the payment screen without the order becoming a mystery to
 * them or to the person who has to chase it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // "I'll pay Mr. Muhindo Mubaraka directly." Lets someone off the
            // payment screen while keeping the invoice open and the content
            // locked, instead of stranding them there or quietly letting them in.
            $table->timestamp('direct_payment_at')->nullable()->after('issued_at');

            // Cancelled by the buyer. The status still moves to Void — this is
            // the "when", so a buyer cancellation stays distinguishable from an
            // invoice voided by staff.
            $table->timestamp('cancelled_at')->nullable()->after('direct_payment_at');

            // Chasing unpaid invoices filters on exactly this pair.
            $table->index(['status', 'direct_payment_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'direct_payment_at']);
            $table->dropColumn(['direct_payment_at', 'cancelled_at']);
        });
    }
};
