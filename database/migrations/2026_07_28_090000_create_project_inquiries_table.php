<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** PUBLIC_SITE_PLAN.md §4 — the "Start a project" client-funnel lead inbox. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_inquiries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('organisation')->nullable();
            $table->string('project_type');
            $table->string('budget_range')->nullable();
            $table->string('timeline')->nullable();
            $table->text('description');
            $table->string('status', 16)->default('new'); // new|contacted|converted|closed
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_inquiries');
    }
};
