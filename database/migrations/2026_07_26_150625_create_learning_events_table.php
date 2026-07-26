<?php

use App\Enums\LearningEventType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('subject'); // quiz attempt, submission, … (P3+)
            $table->enum('event', array_map(fn ($case) => $case->value, LearningEventType::cases()));
            $table->json('value')->nullable(); // position, seconds, score, …
            $table->timestamp('created_at')->useCurrent();

            $table->index(['enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_events');
    }
};
