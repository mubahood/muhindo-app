<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** §5.1/§5.3 — Classroom-style assignment: text/link/file turn-in, optional due date + late penalty. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('points')->default(100);
            $table->boolean('allow_late')->default(true);
            $table->unsignedTinyInteger('late_penalty_percent')->nullable();
            $table->unsignedInteger('max_file_mb')->default(20);
            $table->string('allowed_types')->default('pdf,zip,text,link');
            $table->boolean('resubmit_until_graded')->default(true);
            $table->boolean('is_published')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
