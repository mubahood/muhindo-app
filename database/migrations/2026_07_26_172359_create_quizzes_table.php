<?php

use App\Enums\QuizFeedbackMode;
use App\Enums\QuizGradingMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A quiz is always scoped to a course; a nullable lesson_id makes it a per-lesson quiz vs. a course-level/final one. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedInteger('max_attempts')->nullable();
            $table->unsignedTinyInteger('pass_percent')->default(70);
            $table->string('grading_method', 16)->default(QuizGradingMethod::Highest->value);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedInteger('questions_per_attempt')->nullable();
            $table->boolean('one_question_per_page')->default(false);
            $table->string('feedback_mode', 16)->default(QuizFeedbackMode::AfterSubmit->value);
            $table->boolean('counts_toward_certificate')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
