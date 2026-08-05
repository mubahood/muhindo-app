<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop: digital products, and the licence that proves someone bought one.
 *
 * Deliberately no orders table. Everything purchasable on this site already
 * becomes an Invoice with line items carrying a source model, and payment,
 * settlement and fulfilment all hang off that. A separate order pipeline for
 * the shop would mean two payment paths to keep correct, two places to
 * reconcile money, and a course and an e-book that cannot share a basket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 200)->unique();
            $table->string('type', 40)->default('resource');   // ebook, template, toolkit, resource
            $table->string('summary', 300)->nullable();
            $table->longText('description')->nullable();
            $table->string('category', 80)->nullable();
            $table->json('tags')->nullable();
            $table->string('cover_image')->nullable();

            // Money as decimal, never float, matched to the invoices table so
            // a price can move between them without a rounding step.
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('compare_at_price', 12, 2)->nullable();   // shown struck through
            $table->string('currency', 8)->default('UGX');

            $table->string('file_path')->nullable();           // the deliverable
            $table->string('file_name')->nullable();           // what the buyer sees
            $table->unsignedBigInteger('file_bytes')->nullable();
            $table->string('external_url')->nullable();        // for things hosted elsewhere

            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('purchases_count')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index('category');
        });

        Schema::create('product_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();

            // One licence per person per product: fulfilment runs again on every
            // settle (webhook and callback both fire), and this is what makes a
            // repeat grant a no-op rather than a duplicate.
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_licenses');
        Schema::dropIfExists('products');
    }
};
