<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A piece of client work, tracked from proposal through completion. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('project_number')->unique();

            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->string('status', 16)->default('proposal'); // proposal|active|on_hold|completed|cancelled
            $table->string('priority', 16)->default('medium');  // low|medium|high|urgent

            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 5)->default('UGX');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
