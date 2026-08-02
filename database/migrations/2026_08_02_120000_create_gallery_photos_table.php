<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The gallery.
 *
 * width/height are stored rather than read from the file at render time: the
 * grid reserves each tile's exact aspect ratio before the image arrives, so
 * nothing jumps as photos load. Reading dimensions per request would mean 24
 * filesystem hits on every page view to achieve the same thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('caption', 400)->nullable();
            // Written separately from the caption: a caption is editorial, alt
            // text describes the picture for someone who cannot see it.
            $table->string('alt', 250)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('path');                       // optimised JPEG
            $table->string('webp_path')->nullable();      // smaller modern format
            $table->string('thumb_path')->nullable();     // grid tile
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_photos');
    }
};
