<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §6.5/§4.5 — earned badges (course completions, a perfect quiz, a streak). One row per user per badge, ever. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('badge_type');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'badge_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
