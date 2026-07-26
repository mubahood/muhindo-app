<?php

use App\Enums\QuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §5.1. `softDeletes` added here even though the plan's schema table doesn't
 * list it for `questions` — mirrors the exact P0.3/L7 reasoning: an editor
 * hard-deleting a question after students have already answered it would
 * cascade-delete `attempt_answers`, silently destroying graded history. The
 * plan explicitly calls this pattern out for `quizzes`/`assignments`; a
 * question is the same kind of "content that must survive editing" as a
 * lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->default(QuestionType::McqSingle->value);
            $table->longText('prompt');
            $table->longText('explanation')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
