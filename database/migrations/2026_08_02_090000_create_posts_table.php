<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Insights, the writing side of the site.
 *
 * Deliberately a first-class table rather than another JSON settings blob: a
 * post has its own URL, its own publish date and its own SEO, and none of that
 * works if the content lives inside a single serialised setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('excerpt', 400)->nullable();
            $table->longText('body');
            $table->string('category', 80)->nullable();
            $table->json('tags')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_published')->default(false);
            // Nullable until it is actually published, so "published" and "has a
            // date" can never disagree.
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('read_minutes')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The public index reads exactly this: published, newest first.
            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
