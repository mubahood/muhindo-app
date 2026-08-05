<?php

use App\Enums\QuizAttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per attempt; `question_order` freezes the shuffle/pool-draw so a resumed attempt sees the same questions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt_no');
            $table->string('status', 16)->default(QuizAttemptStatus::InProgress->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->decimal('score_points', 6, 2)->nullable();
            $table->decimal('max_points', 6, 2)->nullable();
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->json('question_order')->nullable();
            $table->json('integrity')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'enrollment_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
