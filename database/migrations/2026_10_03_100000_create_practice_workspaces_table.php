<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title', 120)->default('My practice');
            $table->mediumText('html_content')->nullable();
            $table->mediumText('css_content')->nullable();
            $table->mediumText('js_content')->nullable();
            $table->boolean('bootstrap_enabled')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_workspaces');
    }
};
