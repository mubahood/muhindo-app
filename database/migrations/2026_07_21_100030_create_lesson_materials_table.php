<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('type', 16)->default('file'); // pdf|zip|link|file
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_materials');
    }
};
